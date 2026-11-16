<?php

namespace App\Services\Drive;

use App\Enums\DriveAccess;
use App\Enums\DriveFileKind;
use App\Enums\DriveGranteeType;
use App\Enums\DriveItemType;
use App\Enums\RoleName;
use App\Models\Contracts\DriveItem;
use App\Models\Division;
use App\Models\Document;
use App\Models\DriveActivity;
use App\Models\DriveFile;
use App\Models\DriveStar;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use ZipArchive;

/**
 * Drive internal (AGENTS.md Fase 8, keputusan "Drive mirip Google Drive").
 * Semua aksi diperiksa ulang lewat policy (DriveGate), di dalam transaction, dan dicatat ke
 * drive_activities (+ activity_logs untuk hapus/berbagi). Berkas disimpan ASLI di disk privat.
 */
class DriveService
{
    public const DISK = 'local';

    public const MAX_FILE_KB = 10240;

    public const DEFAULT_QUOTA_MB = 2048;

    public const DEFAULT_TRASH_DAYS = 30;

    public const MAX_ZIP_BYTES = 200 * 1024 * 1024;

    public function __construct(
        private readonly DriveAccessResolver $access,
        private readonly DriveGate $gate,
        private readonly DriveThumbnailer $thumbnailer,
        private readonly ActivityLogger $logger,
    ) {}

    // ---------------------------------------------------------------- kuota

    public static function quotaBytes(): int
    {
        return max(1, (int) (Setting::get('drive_quota_mb') ?? self::DEFAULT_QUOTA_MB)) * 1024 * 1024;
    }

    public static function trashDays(): int
    {
        return max(1, (int) (Setting::get('drive_trash_days') ?? self::DEFAULT_TRASH_DAYS));
    }

    /** Terpakai (termasuk Sampah, seperti Google Drive) dalam byte. */
    public static function usedBytes(): int
    {
        return (int) DriveFile::withTrashed()->sum('size');
    }

    // ---------------------------------------------------------------- buat

    public function createFolder(User $actor, ?int $parentId, string $name): Folder
    {
        $this->authorizeCreateIn($actor, $parentId);
        $name = $this->cleanName($name);

        return DB::transaction(function () use ($actor, $parentId, $name): Folder {
            $folder = new Folder(['name' => $name, 'parent_id' => $parentId]);
            $folder->forceFill(['created_by' => $actor->getKey()])->save();
            $this->record($actor, 'create', $folder);

            return $folder;
        });
    }

    /** Upload satu berkas (multi-file dikirim satu per satu dari browser). */
    public function upload(User $actor, ?int $folderId, UploadedFile $upload): DriveFile
    {
        $this->authorizeCreateIn($actor, $folderId);

        $extension = strtolower($upload->getClientOriginalExtension());
        $kind = DriveFileKind::fromExtension($extension);
        $mime = (string) $upload->getMimeType(); // deteksi isi (finfo), bukan dari ekstensi

        if ($kind === null || ! in_array($mime, $kind->mimeTypes(), true)) {
            throw ValidationException::withMessages(['upload' => "Jenis berkas \"{$upload->getClientOriginalName()}\" tidak diizinkan."]);
        }

        if ($upload->getSize() > self::MAX_FILE_KB * 1024) {
            throw ValidationException::withMessages(['upload' => "\"{$upload->getClientOriginalName()}\" melebihi batas 10 MB."]);
        }

        $this->ensureQuota((int) $upload->getSize());

        $stored = Str::uuid().'.'.$extension;
        $upload->storeAs(DriveFile::DIRECTORY, $stored, self::DISK);

        return $this->persistFile($actor, $folderId, $stored, $upload->getClientOriginalName(), $mime, $extension, $kind, (int) $upload->getSize(), 'upload');
    }

    /**
     * Salin berkas dari disk lain ke Drive (dipakai arsip surat). Kuota tetap dicek.
     * $actor null = sistem (pemeriksaan izin dilewati).
     */
    public function importFile(?User $actor, Folder $folder, string $sourceDisk, string $sourcePath, string $originalName): DriveFile
    {
        if ($actor !== null) {
            $this->authorizeCreateIn($actor, $folder->getKey());
        }

        $source = Storage::disk($sourceDisk);

        if (! $source->exists($sourcePath)) {
            throw ValidationException::withMessages(['upload' => 'Berkas sumber tidak ditemukan.']);
        }

        $size = (int) $source->size($sourcePath);
        $this->ensureQuota($size);

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: pathinfo($sourcePath, PATHINFO_EXTENSION));
        $kind = DriveFileKind::fromExtension($extension) ?? DriveFileKind::Pdf;
        $mime = (string) ($source->mimeType($sourcePath) ?: 'application/octet-stream');
        $stored = Str::uuid().'.'.$extension;

        Storage::disk(self::DISK)->writeStream(DriveFile::DIRECTORY.'/'.$stored, $source->readStream($sourcePath));

        return $this->persistFile($actor, $folder->getKey(), $stored, $originalName, $mime, $extension, $kind, $size, 'archive');
    }

    private function persistFile(?User $actor, ?int $folderId, string $stored, string $originalName, string $mime, string $extension, DriveFileKind $kind, int $size, string $action): DriveFile
    {
        try {
            return DB::transaction(function () use ($actor, $folderId, $stored, $originalName, $mime, $extension, $kind, $size, $action): DriveFile {
                $file = new DriveFile(['folder_id' => $folderId, 'original_name' => Str::limit($this->cleanName($originalName, 255), 255, '')]);
                $file->forceFill([
                    'stored_name' => $stored,
                    'disk' => self::DISK,
                    'mime_type' => Str::limit($mime, 150, ''),
                    'extension' => $extension,
                    'kind' => $kind,
                    'size' => $size,
                    'uploaded_by' => $actor?->getKey(),
                ])->save();

                $thumb = $this->thumbnailer->make($file, Storage::disk(self::DISK)->path($file->path()));

                if ($thumb) {
                    $file->forceFill(['thumbnail_path' => $thumb])->save();
                }

                $this->record($actor, $action, $file);

                return $file;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete(DriveFile::DIRECTORY.'/'.$stored);

            throw $e;
        }
    }

    // ---------------------------------------------------------------- ubah

    public function rename(User $actor, DriveItem&Model $item, string $name): DriveItem&Model
    {
        $this->authorize($actor, 'update', $item);
        $name = $this->cleanName($name, $item instanceof DriveFile ? 255 : ($item instanceof Document ? 200 : 150));

        if ($item instanceof DriveFile) {
            $extension = '.'.$item->extension;
            $name = Str::endsWith(strtolower($name), $extension) ? $name : $name.$extension;
        }

        return DB::transaction(function () use ($actor, $item, $name) {
            $item->forceFill([$this->nameColumn($item) => $name])->save();
            $this->record($actor, 'rename', $item);

            return $item;
        });
    }

    public function move(User $actor, DriveItem&Model $item, ?int $targetFolderId): DriveItem&Model
    {
        $this->authorize($actor, 'update', $item);
        $this->authorizeCreateIn($actor, $targetFolderId);

        if ($item instanceof Folder && $targetFolderId !== null && $this->access->isDescendantOf($targetFolderId, (int) $item->getKey())) {
            throw ValidationException::withMessages(['target' => 'Folder tidak bisa dipindah ke dirinya sendiri atau ke dalam subfoldernya.']);
        }

        if ($targetFolderId !== null && Folder::query()->whereKey($targetFolderId)->doesntExist()) {
            throw ValidationException::withMessages(['target' => 'Folder tujuan tidak ditemukan.']);
        }

        return DB::transaction(function () use ($actor, $item, $targetFolderId) {
            $item->forceFill([$item instanceof Folder ? 'parent_id' : 'folder_id' => $targetFolderId])->save();
            $this->record($actor, 'move', $item, $targetFolderId);

            return $item;
        });
    }

    // ---------------------------------------------------------------- sampah

    /** Buang ke Sampah. Folder ikut membuang seluruh isinya dalam satu batch (dipulihkan bersama). */
    public function trash(User $actor, DriveItem&Model $item): void
    {
        $this->authorize($actor, 'delete', $item);

        DB::transaction(function () use ($actor, $item): void {
            $batch = (string) Str::uuid();
            $stamp = ['deleted_by' => $actor->getKey(), 'trash_batch' => $batch];

            if ($item instanceof Folder) {
                $ids = $this->access->descendantIds((int) $item->getKey());
                $now = now();

                DriveFile::query()->whereIn('folder_id', $ids)->update([...$stamp, 'deleted_at' => $now]);
                Document::query()->whereIn('folder_id', $ids)->update([...$stamp, 'deleted_at' => $now]);
                Folder::query()->whereIn('id', $ids)->update([...$stamp, 'deleted_at' => $now]);
                $this->access->flush();
            } else {
                $item->forceFill($stamp)->save();
                $item->delete();
            }

            $this->record($actor, 'delete', $item);
            $this->logger->log('drive.trashed', $item, ['type' => $item->driveType()->value, 'name' => $item->driveName()], $actor);
        });
    }

    public function restore(User $actor, DriveItem&Model $item): void
    {
        $this->authorize($actor, 'restore', $item);

        DB::transaction(function () use ($actor, $item): void {
            $batch = $item->getAttribute('trash_batch');
            $clear = ['deleted_at' => null, 'deleted_by' => null, 'trash_batch' => null];

            if ($batch) {
                foreach ([DriveFile::class, Document::class, Folder::class] as $model) {
                    $model::onlyTrashed()->where('trash_batch', $batch)->update($clear);
                }
            } else {
                $item->restore();
            }

            $item->refresh();

            // Folder induk sudah tidak ada / masih di Sampah (batch lain) → pulihkan ke root.
            $parentId = $item->driveFolderId();

            if ($parentId !== null && Folder::query()->whereKey($parentId)->doesntExist()) {
                $item->forceFill([$item instanceof Folder ? 'parent_id' : 'folder_id' => null])->save();
            }

            $this->access->flush();
            $this->record($actor, 'restore', $item);
            $this->logger->log('drive.restored', $item, ['type' => $item->driveType()->value, 'name' => $item->driveName()], $actor);
        });
    }

    public function forceDelete(User $actor, DriveItem&Model $item): void
    {
        $this->authorize($actor, 'forceDelete', $item);

        $this->destroy($item, $actor);
    }

    /** Hapus permanen isi Sampah yang lebih tua dari N hari (scheduler). @return int jumlah item */
    public function purgeTrash(): int
    {
        $cutoff = now()->subDays(self::trashDays());
        $count = 0;

        foreach ([DriveFile::class, Document::class] as $model) {
            $model::onlyTrashed()->where('deleted_at', '<=', $cutoff)->chunkById(100, function ($items) use (&$count): void {
                foreach ($items as $item) {
                    $this->destroy($item, null);
                    $count++;
                }
            });
        }

        // Folder: yang terdalam dulu (FK restrict), hanya bila sudah kosong.
        $folders = Folder::onlyTrashed()->where('deleted_at', '<=', $cutoff)->where('is_system', false)->get();

        foreach ($folders->sortByDesc(fn (Folder $f) => count($this->ancestry($f))) as $folder) {
            if ($this->folderIsEmpty($folder)) {
                $this->destroy($folder, null);
                $count++;
            }
        }

        return $count;
    }

    private function destroy(DriveItem&Model $item, ?User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            if ($item instanceof Folder) {
                $ids = array_reverse($this->access->descendantIds((int) $item->getKey()));

                foreach (DriveFile::withTrashed()->whereIn('folder_id', $ids)->get() as $file) {
                    $this->destroyFile($file);
                }

                Document::withTrashed()->whereIn('folder_id', $ids)->get()->each->forceDelete();
                FolderPermission::query()->whereIn('folder_id', $ids)->delete();
                DriveStar::query()->where('item_type', DriveItemType::Folder->value)->whereIn('item_id', $ids)->delete();

                // Anak dulu baru induk (FK restrict).
                foreach (Folder::withTrashed()->whereIn('id', $ids)->get()->sortByDesc(fn (Folder $f) => count($this->ancestry($f))) as $folder) {
                    $folder->forceDelete();
                }
            } elseif ($item instanceof DriveFile) {
                $this->destroyFile($item);
            } else {
                DriveStar::query()->where('item_type', DriveItemType::Document->value)->where('item_id', $item->getKey())->delete();
                $item->forceDelete();
            }

            $this->access->flush();

            // Catat di folder induk yang masih ada (folder yang dihapus sudah tidak bisa dirujuk).
            $parentId = $item->driveFolderId();
            DriveActivity::query()->create([
                'user_id' => $actor?->getKey(),
                'action' => 'force_delete',
                'item_type' => $item->driveType()->value,
                'item_id' => $item->getKey(),
                'item_name' => Str::limit($item->driveName(), 250, ''),
                'folder_id' => $parentId !== null && Folder::withTrashed()->whereKey($parentId)->exists() ? $parentId : null,
            ]);
            $this->logger->log('drive.force_deleted', null, [
                'type' => $item->driveType()->value, 'id' => $item->getKey(), 'name' => $item->driveName(),
            ], $actor, recordIp: $actor !== null);
        });
    }

    private function destroyFile(DriveFile $file): void
    {
        Storage::disk($file->disk)->delete(array_filter([$file->path(), $file->thumbnail_path]));
        DriveStar::query()->where('item_type', DriveItemType::File->value)->where('item_id', $file->getKey())->delete();
        $file->forceDelete();
    }

    // ---------------------------------------------------------------- berbagi

    public function share(User $actor, Folder $folder, DriveGranteeType $type, int $granteeId, DriveAccess $access): FolderPermission
    {
        $this->authorize($actor, 'share', $folder);
        $this->assertValidGrantee($type, $granteeId);

        return DB::transaction(function () use ($actor, $folder, $type, $granteeId, $access): FolderPermission {
            $permission = FolderPermission::query()->updateOrCreate(
                ['folder_id' => $folder->getKey(), 'grantee_type' => $type->value, 'grantee_id' => $granteeId],
                ['access' => $access->value, 'granted_by' => $actor->getKey()],
            );

            $this->record($actor, 'share', $folder);
            $this->logger->log('drive.shared', $folder, ['grantee_type' => $type->value, 'grantee_id' => $granteeId, 'access' => $access->value], $actor);

            return $permission;
        });
    }

    public function unshare(User $actor, FolderPermission $permission): void
    {
        $this->authorize($actor, 'share', $permission->folder);

        DB::transaction(function () use ($actor, $permission): void {
            $this->logger->log('drive.unshared', $permission->folder, [
                'grantee_type' => $permission->grantee_type->value, 'grantee_id' => $permission->grantee_id,
            ], $actor);
            $permission->delete();
        });
    }

    /** Pengaju kegiatan tidak pernah bisa menerima izin Drive. */
    private function assertValidGrantee(DriveGranteeType $type, int $id): void
    {
        $valid = match ($type) {
            DriveGranteeType::Role => Role::query()->whereKey($id)->where('guard_name', 'web')
                ->whereNotIn('name', [RoleName::PengajuKegiatan->value, RoleName::SuperAdmin->value])->exists(),
            DriveGranteeType::Division => Division::query()->whereKey($id)->exists(),
            DriveGranteeType::User => ($user = User::query()->find($id)) !== null
                && $user->is_active && ! $user->isApplicant() && $user->roles()->exists(),
        };

        if (! $valid) {
            throw ValidationException::withMessages(['grantee_id' => 'Penerima tidak valid. Folder hanya bisa dibagikan ke pengurus BEM.']);
        }
    }

    // ---------------------------------------------------------------- bintang & aktivitas

    public function toggleStar(User $user, DriveItem&Model $item): bool
    {
        if (! Gate::forUser($user)->allows('view', $item)) {
            throw new AuthorizationException('Item tidak ditemukan.');
        }

        $attributes = ['user_id' => $user->getKey(), 'item_type' => $item->driveType()->value, 'item_id' => $item->getKey()];
        $existing = DriveStar::query()->where($attributes)->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        DriveStar::query()->create($attributes);

        return true;
    }

    public function recordOpen(User $user, DriveItem&Model $item): void
    {
        $this->record($user, 'open', $item);
    }

    public function record(?User $user, string $action, DriveItem&Model $item, ?int $folderId = null): void
    {
        DriveActivity::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'item_type' => $item->driveType()->value,
            'item_id' => $item->getKey(),
            'item_name' => Str::limit($item->driveName(), 250, ''),
            'folder_id' => $folderId ?? ($item instanceof Folder ? $item->getKey() : $item->driveFolderId()),
        ]);
    }

    // ---------------------------------------------------------------- ZIP

    /**
     * ZIP satu folder (hanya item yang boleh dilihat; tanpa Sampah). Dokumen disertakan sebagai .html.
     * Ditulis ke file sementara (ZipArchive, tanpa library streaming) — hapus setelah dikirim.
     */
    public function zip(User $actor, Folder $folder): string
    {
        $this->authorize($actor, 'view', $folder);

        $ids = array_values(array_filter(
            $this->access->descendantIds((int) $folder->getKey()),
            fn (int $id): bool => $this->access->can($actor, $id, DriveAccess::View),
        ));

        $folders = Folder::query()->whereIn('id', $ids)->get()->keyBy('id');
        $files = DriveFile::query()->whereIn('folder_id', $folders->keys())->get();

        if ($files->sum('size') > self::MAX_ZIP_BYTES) {
            throw ValidationException::withMessages(['zip' => 'Isi folder lebih dari 200 MB. Unduh per subfolder.']);
        }

        $relative = function (?int $id) use ($folders, $folder): string {
            $parts = [];

            while ($id !== null && $folders->has($id) && $id !== $folder->getKey()) {
                array_unshift($parts, $this->safeZipName($folders[$id]->name));
                $id = $folders[$id]->parent_id;
            }

            return $parts === [] ? '' : implode('/', $parts).'/';
        };

        $path = tempnam(sys_get_temp_dir(), 'drive-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($folders as $id => $sub) {
            if ($id !== $folder->getKey()) {
                $zip->addEmptyDir(rtrim($relative($id), '/'));
            }
        }

        $used = [];

        foreach ($files as $file) {
            $name = $this->uniqueZipName($relative($file->folder_id).$this->safeZipName($file->original_name), $used);
            $zip->addFile(Storage::disk($file->disk)->path($file->path()), $name);
        }

        foreach (Document::query()->whereIn('folder_id', $folders->keys())->get() as $document) {
            $name = $this->uniqueZipName($relative($document->folder_id).$this->safeZipName($document->title).'.html', $used);
            $zip->addFromString($name, '<!doctype html><meta charset="utf-8"><title>'.e($document->title).'</title>'.$document->safeContent());
        }

        $zip->close();
        $this->record($actor, 'open', $folder);

        return $path;
    }

    // ---------------------------------------------------------------- bantuan

    /** @return list<int> */
    public function ancestry(Folder $folder): array
    {
        $chain = [];
        $parent = $folder->parent_id;

        while ($parent !== null && ! in_array($parent, $chain, true)) {
            $chain[] = $parent;
            $parent = Folder::withTrashed()->whereKey($parent)->value('parent_id');
        }

        return $chain;
    }

    private function folderIsEmpty(Folder $folder): bool
    {
        return Folder::withTrashed()->where('parent_id', $folder->getKey())->doesntExist()
            && DriveFile::withTrashed()->where('folder_id', $folder->getKey())->doesntExist()
            && Document::withTrashed()->where('folder_id', $folder->getKey())->doesntExist();
    }

    private function ensureQuota(int $bytes): void
    {
        if (self::usedBytes() + $bytes > self::quotaBytes()) {
            throw ValidationException::withMessages(['upload' => 'Penyimpanan Drive penuh. Kosongkan Sampah atau hapus berkas yang tidak dipakai.']);
        }
    }

    private function authorize(User $actor, string $ability, Model $item): void
    {
        if (Gate::forUser($actor)->denies($ability, $item)) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }

    private function authorizeCreateIn(User $actor, ?int $folderId): void
    {
        if ($folderId !== null && Folder::query()->whereKey($folderId)->doesntExist()) {
            throw ValidationException::withMessages(['folder' => 'Folder tidak ditemukan.']);
        }

        if (! $this->gate->createIn($actor, $folderId)) {
            throw new AuthorizationException('Anda tidak punya akses unggah di folder ini.');
        }
    }

    private function cleanName(string $name, int $max = 150): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $name) ?? '');

        if ($name === '' || $name === '.' || $name === '..') {
            throw ValidationException::withMessages(['name' => 'Nama tidak boleh kosong.']);
        }

        if (mb_strlen($name) > $max) {
            throw ValidationException::withMessages(['name' => "Nama maksimal {$max} karakter."]);
        }

        return $name;
    }

    private function nameColumn(Model $item): string
    {
        return match (true) {
            $item instanceof DriveFile => 'original_name',
            $item instanceof Document => 'title',
            default => 'name',
        };
    }

    private function safeZipName(string $name): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $name) ?? '', ' .') ?: 'tanpa-nama';
    }

    /** @param  array<string, true>  $used */
    private function uniqueZipName(string $name, array &$used): string
    {
        $candidate = $name;
        $i = 2;

        while (isset($used[strtolower($candidate)])) {
            $info = pathinfo($name);
            $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';
            $candidate = $dir.$info['filename'].' ('.$i++.')'.(isset($info['extension']) ? '.'.$info['extension'] : '');
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }
}
