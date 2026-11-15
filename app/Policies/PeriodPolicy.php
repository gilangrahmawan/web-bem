<?php

namespace App\Policies;

use App\Models\Period;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

class PeriodPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['delete', 'deleteAny'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_period');
    }

    public function view(User $user, Period $period): bool
    {
        return $user->can('view_period');
    }

    public function create(User $user): bool
    {
        return $user->can('create_period');
    }

    public function update(User $user, Period $period): bool
    {
        return $user->can('update_period');
    }

    public function activate(User $user, Period $period): bool
    {
        return ! $period->is_active && $user->can('update_period');
    }

    /** Periode aktif tidak boleh dihapus. */
    public function delete(User $user, Period $period): bool
    {
        return $user->can('delete_period') && ! $period->is_active;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
