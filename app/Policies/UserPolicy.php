<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

class UserPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return [
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny',
            'restore', 'restoreAny', 'replicate', 'deactivate', 'resetTwoFactor',
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('view_user');
    }

    public function create(User $user): bool
    {
        return $user->can('create_user');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('update_user') && $this->canTouch($user, $model);
    }

    public function manageRoles(User $user, User $model): bool
    {
        return $user->can('user.manage') && $this->canTouch($user, $model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->can('user.manage') && $this->canTouch($user, $model);
    }

    /** Reset 2FA (HP hilang): user.manage (Super Admin), bukan untuk diri sendiri. */
    public function resetTwoFactor(User $user, User $model): bool
    {
        return $user->can('user.manage') && ! $user->is($model) && $this->canTouch($user, $model);
    }

    public function activate(User $user, User $model): bool
    {
        return ! $model->is_active
            && $user->can('user.manage')
            && $this->canTouch($user, $model);
    }

    public function deactivate(User $user, User $model): bool
    {
        if (! $model->is_active || $user->is($model)) {
            return false;
        }

        if (! $user->can('user.manage') || ! $this->canTouch($user, $model)) {
            return false;
        }

        // Jangan sampai Super Admin aktif habis.
        return ! $model->isSuperAdmin()
            || User::query()->activeSuperAdmins()->whereKeyNot($model->getKey())->exists();
    }

    // User tidak pernah dihapus (AGENTS.md B4), termasuk oleh super_admin.
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, User $model): bool
    {
        return false;
    }

    /** Akun Super Admin hanya boleh diubah oleh Super Admin. */
    private function canTouch(User $actor, User $target): bool
    {
        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }
}
