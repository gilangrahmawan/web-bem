<?php

namespace App\Services\Drive;

use App\Enums\DriveItemType;
use App\Models\Contracts\DriveItem;
use App\Models\Document;
use App\Models\DriveActivity;
use App\Models\DriveFile;
use App\Models\DriveStar;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Query isi Drive per bagian (Drive Saya, Dibagikan, Terbaru, Berbintang, Sampah, folder, cari).
 * Semua hasil SUDAH difilter hak lihat — halaman tidak pernah menampilkan item yang tidak boleh dilihat.
 */
class DriveBrowser
{
    public const LIMIT = 300;

    public function __construct(private readonly DriveAccessResolver $access) {}

    /** @return array{folders: Collection<int, Folder>, files: Collection<int, DriveFile>, documents: Collection<int, Document>} */
    public function folderContents(User $user, ?int $folderId): array
    {
        return [
            'folders' => Folder::query()->where('parent_id', $folderId)->orderBy('is_system', 'desc')->orderBy('name')->limit(self::LIMIT)->get(),
            'files' => DriveFile::query()->with('uploader:id,name')->where('folder_id', $folderId)->orderBy('original_name')->limit(self::LIMIT)->get(),
            'documents' => Document::query()->with('editor:id,name')->where('folder_id', $folderId)->orderBy('title')->limit(self::LIMIT)->get(),
        ];
    }

    /**
     * Drive Saya: pengelola Drive & Pembina (lihat semua) → isi root; pengurus lain → item yang ia buat/unggah sendiri.
     *
     * @return array{folders: Collection<int, Folder>, files: Collection<int, DriveFile>, documents: Collection<int, Document>}
     */
    public function mine(User $user): array
    {
        if ($this->access->seesAll($user)) {
            return $this->folderContents($user, null);
        }

        $visible = $this->access->visibleFolderIds($user);

        return [
            'folders' => $this->scopeFolders(Folder::query()->where('created_by', $user->getKey()), $visible)->orderBy('name')->limit(self::LIMIT)->get(),
            'files' => $this->scopeItems(DriveFile::query()->with('uploader:id,name')->where('uploaded_by', $user->getKey()), $visible)->latest()->limit(self::LIMIT)->get(),
            'documents' => $this->scopeItems(Document::query()->with('editor:id,name')->where('created_by', $user->getKey()), $visible)->latest()->limit(self::LIMIT)->get(),
        ];
    }

    /** @return Collection<int, Folder> */
    public function shared(User $user): Collection
    {
        $ids = $this->access->seesAll($user)
            ? FolderPermission::query()->distinct()->pluck('folder_id')->all()
            : $this->access->sharedRootIds($user);

        return Folder::query()->whereIn('id', $ids ?: [0])->orderBy('name')->get();
    }

    /** Item yang baru dibuka/diubah oleh user ini (unik, masih ada, masih boleh dilihat). @return Collection<int, Model&DriveItem> */
    public function recent(User $user, int $limit = 30): Collection
    {
        $rows = DriveActivity::query()->where('user_id', $user->getKey())
            ->whereIn('item_type', [DriveItemType::File->value, DriveItemType::Document->value])
            ->where('action', '!=', 'delete')->where('action', '!=', 'force_delete')
            ->latest('id')->limit(200)->get(['item_type', 'item_id']);

        $items = $this->hydrate($rows->unique(fn ($r) => $r->item_type->value.':'.$r->item_id)->take($limit * 2));

        // Pengelola Drive yang belum punya riwayat tetap melihat berkas terbaru.
        if ($items->count() < $limit) {
            $visible = $this->access->visibleFolderIds($user);
            $extra = $this->scopeItems(DriveFile::query(), $visible)->latest()->limit($limit)->get()
                ->concat($this->scopeItems(Document::query(), $visible)->latest('updated_at')->limit($limit)->get());
            $items = $items->concat($extra)->unique(fn ($i) => $i->driveType()->value.':'.$i->getKey());
        }

        return $items->filter(fn ($item) => Gate::forUser($user)->allows('view', $item))->take($limit)->values();
    }

    /** Folder disarankan: folder yang baru diakses/diubah user, lalu folder teratas yang terlihat. @return Collection<int, Folder> */
    public function suggestedFolders(User $user, int $limit = 8): Collection
    {
        $ids = DriveActivity::query()->where('user_id', $user->getKey())->whereNotNull('folder_id')
            ->latest('id')->limit(100)->pluck('folder_id')->unique()->take($limit * 2)->all();

        $folders = Folder::query()->whereIn('id', $ids ?: [0])->get()->sortBy(fn (Folder $f) => array_search($f->getKey(), $ids, true));

        $fallback = $this->access->seesAll($user)
            ? Folder::query()->whereNull('parent_id')->orderByDesc('is_system')->orderBy('name')->limit($limit)->get()
            : $this->shared($user);

        return $folders->concat($fallback)->unique('id')
            ->filter(fn (Folder $f) => Gate::forUser($user)->allows('view', $f))
            ->take($limit)->values();
    }

    /** @return Collection<int, Model&DriveItem> */
    public function starred(User $user): Collection
    {
        $rows = DriveStar::query()->where('user_id', $user->getKey())->latest('id')->get(['item_type', 'item_id']);

        return $this->hydrate($rows)->filter(fn ($item) => Gate::forUser($user)->allows('view', $item))->values();
    }

    /** Isi Sampah yang boleh dipulihkan user, hanya item teratas per batch. @return Collection<int, Model&DriveItem> */
    public function trash(User $user): Collection
    {
        $items = Folder::onlyTrashed()->latest('deleted_at')->limit(self::LIMIT)->get()
            ->concat(DriveFile::onlyTrashed()->latest('deleted_at')->limit(self::LIMIT)->get())
            ->concat(Document::onlyTrashed()->latest('deleted_at')->limit(self::LIMIT)->get());

        $trashedFolders = Folder::onlyTrashed()->get(['id', 'trash_batch'])->keyBy('id');

        return $items
            ->reject(function ($item) use ($trashedFolders): bool {
                $parent = $item->driveFolderId();

                // Sembunyikan anak yang ikut terbuang bersama foldernya (dipulihkan lewat foldernya).
                return $parent !== null && $trashedFolders->has($parent)
                    && $trashedFolders[$parent]->trash_batch === $item->getAttribute('trash_batch');
            })
            ->filter(fn ($item) => Gate::forUser($user)->allows('restore', $item))
            ->sortByDesc('deleted_at')->values();
    }

    /** @return Collection<int, Model&DriveItem> */
    public function search(User $user, string $term, int $limit = 100): Collection
    {
        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';
        $visible = $this->access->visibleFolderIds($user);

        return $this->scopeFolders(Folder::query()->where('name', 'like', $term), $visible)->limit($limit)->get()
            ->concat($this->scopeItems(DriveFile::query()->with('uploader:id,name')->where('original_name', 'like', $term), $visible)->limit($limit)->get())
            ->concat($this->scopeItems(Document::query()->with('editor:id,name')->where('title', 'like', $term), $visible)->limit($limit)->get())
            ->take($limit)->values();
    }

    /** @return Collection<int, Folder> folder induk dari root sampai folder ini (yang boleh dilihat saja). */
    public function breadcrumbs(User $user, Folder $folder): Collection
    {
        $chain = collect([$folder]);
        $parent = $folder->parent;

        while ($parent !== null && ! $chain->contains('id', $parent->getKey())) {
            $chain->prepend($parent);
            $parent = $parent->parent;
        }

        return $chain->filter(fn (Folder $f) => ! $f->trashed() && Gate::forUser($user)->allows('view', $f))->values();
    }

    /** Folder tujuan pindah yang boleh diisi user (akses ≥ upload). @return array<int|string, string> */
    public function moveTargets(User $user, ?Folder $moving = null): array
    {
        $exclude = $moving ? $this->access->descendantIds((int) $moving->getKey()) : [];
        $options = [];

        if ($this->access->isManager($user)) {
            $options['root'] = '— Drive (root) —';
        }

        foreach (Folder::query()->whereNotIn('id', $exclude ?: [0])->orderBy('name')->get() as $folder) {
            if (app(DriveGate::class)->createIn($user, (int) $folder->getKey())) {
                $options[$folder->getKey()] = $this->pathLabel($folder);
            }
        }

        return $options;
    }

    public function pathLabel(Folder $folder): string
    {
        $names = [$folder->name];
        $parent = $folder->parent;
        $guard = 0;

        while ($parent !== null && $guard++ < 20) {
            array_unshift($names, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' / ', $names);
    }

    /** @param  Collection<int, object{item_type: DriveItemType, item_id: int}>  $rows  @return Collection<int, Model&DriveItem> */
    private function hydrate(Collection $rows): Collection
    {
        $byType = $rows->groupBy(fn ($r) => $r->item_type->value);
        $models = collect()
            ->concat(Folder::query()->whereIn('id', $byType->get('folder', collect())->pluck('item_id')->all() ?: [0])->get())
            ->concat(DriveFile::query()->with('uploader:id,name')->whereIn('id', $byType->get('file', collect())->pluck('item_id')->all() ?: [0])->get())
            ->concat(Document::query()->with('editor:id,name')->whereIn('id', $byType->get('document', collect())->pluck('item_id')->all() ?: [0])->get())
            ->keyBy(fn ($m) => $m->driveType()->value.':'.$m->getKey());

        return $rows->map(fn ($r) => $models->get($r->item_type->value.':'.$r->item_id))->filter()->values();
    }

    /** @param  list<int>|null  $visible */
    private function scopeFolders(Builder $query, ?array $visible): Builder
    {
        return $visible === null ? $query : $query->whereIn('id', $visible ?: [0]);
    }

    /** @param  list<int>|null  $visible */
    private function scopeItems(Builder $query, ?array $visible): Builder
    {
        return $visible === null ? $query : $query->whereIn('folder_id', $visible ?: [0]);
    }
}
