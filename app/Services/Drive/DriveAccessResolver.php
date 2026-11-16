<?php

namespace App\Services\Drive;

use App\Enums\DriveAccess;
use App\Enums\DriveGranteeType;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;

/**
 * Akses efektif Drive (AGENTS.md D3 + keputusan Fase 8):
 * - Super Admin & pemegang `folder.manage` (Sekretaris): manage di semua folder, termasuk root.
 * - Pembina (`report.view`): view di semua folder (read-only).
 * - Pengurus lain: akses tertinggi dari izin folder itu ATAU folder induk mana pun (role/divisi/user).
 * - Pengaju kegiatan & user nonaktif: tidak punya akses sama sekali.
 *
 * Pohon folder & izin dimuat sekali per request (resolver di-bind scoped), lalu di-memo per user.
 */
class DriveAccessResolver
{
    /** @var array<int, int|null>|null folder_id => parent_id (termasuk yang di Sampah) */
    private ?array $tree = null;

    /** @var array<int, array<int, DriveAccess>> user_id => [folder_id => akses langsung] */
    private array $grants = [];

    /** @var array<string, DriveAccess|null> */
    private array $memo = [];

    /** @var array<string, bool> */
    private array $flags = [];

    public function canUseDrive(User $user): bool
    {
        return $this->flag($user, 'use', fn (): bool => $user->is_active && ! $user->isApplicant() && $user->roles()->exists());
    }

    public function isManager(User $user): bool
    {
        return $this->flag($user, 'manager', fn (): bool => $this->canUseDrive($user)
            && ($user->isSuperAdmin() || $user->checkPermissionTo('folder.manage')));
    }

    public function seesAll(User $user): bool
    {
        return $this->flag($user, 'all', fn (): bool => $this->isManager($user)
            || ($this->canUseDrive($user) && $user->checkPermissionTo('report.view')));
    }

    private function flag(User $user, string $name, \Closure $compute): bool
    {
        $key = $user->getKey().':'.$name.':'.(int) $user->is_active;

        return $this->flags[$key] ??= $compute();
    }

    /** Akses efektif pada folder (null = root Drive). */
    public function accessFor(User $user, Folder|int|null $folder): ?DriveAccess
    {
        if (! $this->canUseDrive($user)) {
            return null;
        }

        if ($this->isManager($user)) {
            return DriveAccess::Manage;
        }

        if ($this->seesAll($user)) {
            return DriveAccess::View;
        }

        $folderId = $folder instanceof Folder ? (int) $folder->getKey() : $folder;

        if ($folderId === null) {
            return null; // Root hanya untuk pengelola Drive.
        }

        $key = $user->getKey().':'.$folderId;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $grants = $this->grantsFor($user);
        $tree = $this->tree();
        $access = null;
        $seen = [];

        for ($id = $folderId; $id !== null && ! isset($seen[$id]); $id = $tree[$id] ?? null) {
            $seen[$id] = true;
            $access = DriveAccess::max($access, $grants[$id] ?? null);

            if ($access === DriveAccess::Manage) {
                break;
            }
        }

        return $this->memo[$key] = $access;
    }

    public function can(User $user, Folder|int|null $folder, DriveAccess $needed): bool
    {
        return $this->accessFor($user, $folder)?->atLeast($needed) ?? false;
    }

    /**
     * ID folder yang boleh dilihat user (null = semua).
     *
     * @return list<int>|null
     */
    public function visibleFolderIds(User $user): ?array
    {
        if ($this->seesAll($user)) {
            return null;
        }

        if (! $this->canUseDrive($user)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($this->tree()),
            fn (int $id): bool => $this->accessFor($user, $id) !== null,
        ));
    }

    /**
     * Folder teratas yang dibagikan kepada user (izin langsung, tanpa induk yang juga terlihat).
     *
     * @return list<int>
     */
    public function sharedRootIds(User $user): array
    {
        if (! $this->canUseDrive($user) || $this->seesAll($user)) {
            return [];
        }

        $tree = $this->tree();

        return array_values(array_filter(
            array_keys($this->grantsFor($user)),
            fn (int $id): bool => array_key_exists($id, $tree) && ($tree[$id] === null || $this->accessFor($user, $tree[$id]) === null),
        ));
    }

    /** @return list<int> ID folder turunan (rekursif) termasuk dirinya. */
    public function descendantIds(int $folderId): array
    {
        $children = [];

        foreach ($this->tree() as $id => $parent) {
            if ($parent !== null) {
                $children[$parent][] = $id;
            }
        }

        $result = [];
        $stack = [$folderId];

        while ($stack !== []) {
            $id = array_pop($stack);

            if (isset($result[$id])) {
                continue;
            }

            $result[$id] = true;
            array_push($stack, ...($children[$id] ?? []));
        }

        return array_map('intval', array_keys($result));
    }

    public function isDescendantOf(int $folderId, int $ancestorId): bool
    {
        return in_array($folderId, $this->descendantIds($ancestorId), true);
    }

    /** Panggil setelah struktur folder atau izin berubah. */
    public function flush(): void
    {
        $this->tree = null;
        $this->grants = [];
        $this->memo = [];
        $this->flags = [];
    }

    /** @return array<int, int|null> */
    private function tree(): array
    {
        return $this->tree ??= Folder::withTrashed()->pluck('parent_id', 'id')
            ->map(fn ($parent) => $parent === null ? null : (int) $parent)
            ->all();
    }

    /** @return array<int, DriveAccess> */
    private function grantsFor(User $user): array
    {
        $userId = (int) $user->getKey();

        if (isset($this->grants[$userId])) {
            return $this->grants[$userId];
        }

        $roleIds = $user->roles()->pluck('id')->all();

        $rows = FolderPermission::query()
            ->where(function ($query) use ($user, $roleIds): void {
                $query->where(fn ($q) => $q->where('grantee_type', DriveGranteeType::User->value)->where('grantee_id', $user->getKey()))
                    ->orWhere(fn ($q) => $q->where('grantee_type', DriveGranteeType::Role->value)->whereIn('grantee_id', $roleIds ?: [0]))
                    ->when($user->division_id, fn ($q) => $q->orWhere(fn ($q2) => $q2
                        ->where('grantee_type', DriveGranteeType::Division->value)->where('grantee_id', $user->division_id)));
            })
            ->get(['folder_id', 'access']);

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->folder_id] = DriveAccess::max($map[(int) $row->folder_id] ?? null, $row->access);
        }

        return $this->grants[$userId] = $map;
    }
}
