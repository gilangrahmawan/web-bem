<?php

namespace App\Services\Drive;

use App\Enums\DriveAccess;
use App\Enums\DriveGranteeType;
use App\Models\Division;
use App\Models\Folder;
use App\Models\FolderPermission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Folder sistem Drive (tidak bisa diganti nama, dipindah, atau dibuang — juga oleh Super Admin).
 * Dibuat lazy & idempoten (bukan di migration, karena role/divisi baru ada setelah seeder).
 *
 * "Surat" (keputusan Fase 8): view untuk divisi Humas, upload untuk Koordinator Humas;
 * Sekretaris & Super Admin otomatis manage. Subfolder per tahun juga folder sistem.
 */
class SystemFolders
{
    public const LETTERS = 'letters';

    public function letters(): Folder
    {
        $existing = Folder::query()->where('system_key', self::LETTERS)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function (): Folder {
            $folder = $this->create(self::LETTERS, 'Surat', null);

            if ($folder->wasRecentlyCreated) {
                $this->grant($folder, DriveGranteeType::Division, Division::query()->where('slug', 'humas')->value('id'), DriveAccess::View);
                $this->grant($folder, DriveGranteeType::Role, Role::query()->where('name', 'koordinator_humas')->where('guard_name', 'web')->value('id'), DriveAccess::Upload);
            }

            return $folder;
        });
    }

    public function lettersYear(int $year): Folder
    {
        $parent = $this->letters();

        return Folder::query()->where('system_key', self::LETTERS.':'.$year)->first()
            ?? $this->create(self::LETTERS.':'.$year, (string) $year, $parent->getKey());
    }

    private function create(string $key, string $name, ?int $parentId): Folder
    {
        $folder = Folder::withTrashed()->firstOrNew(['system_key' => $key]);

        if ($folder->trashed()) {
            $folder->restore();
        }

        if (! $folder->exists) {
            $folder->forceFill(['name' => $name, 'parent_id' => $parentId, 'is_system' => true, 'system_key' => $key])->save();
            $folder->wasRecentlyCreated = true;
        }

        return $folder;
    }

    private function grant(Folder $folder, DriveGranteeType $type, int|string|null $id, DriveAccess $access): void
    {
        if ($id === null) {
            return;
        }

        FolderPermission::query()->firstOrCreate(
            ['folder_id' => $folder->getKey(), 'grantee_type' => $type->value, 'grantee_id' => (int) $id],
            ['access' => $access->value],
        );
    }
}
