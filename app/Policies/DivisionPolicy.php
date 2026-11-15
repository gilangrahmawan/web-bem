<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

class DivisionPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['delete', 'deleteAny'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_division');
    }

    public function view(User $user, Division $division): bool
    {
        return $user->can('view_division');
    }

    public function create(User $user): bool
    {
        return $user->can('create_division');
    }

    public function update(User $user, Division $division): bool
    {
        return $user->can('update_division');
    }

    /** Divisi yang masih punya anggota tidak boleh dihapus. */
    public function delete(User $user, Division $division): bool
    {
        return $user->can('delete_division') && ! $division->users()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
