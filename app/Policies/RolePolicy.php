<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;
use Spatie\Permission\Models\Role;

/**
 * Didaftarkan otomatis oleh Filament Shield (register_role_policy = true).
 * Role super_admin terkunci: tidak bisa diedit/dihapus siapa pun.
 */
class RolePolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'replicate'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_role');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('view_role');
    }

    public function create(User $user): bool
    {
        return $user->can('create_role');
    }

    public function update(User $user, Role $role): bool
    {
        return ! $this->isLocked($role) && $user->can('update_role');
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $this->isLocked($role) && $user->can('delete_role');
    }

    // Hapus massal dimatikan agar role terkunci tidak ikut terhapus.
    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Role $role): bool
    {
        return false;
    }

    private function isLocked(Role $role): bool
    {
        return $role->name === RoleName::SuperAdmin->value;
    }
}
