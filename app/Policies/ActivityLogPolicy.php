<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/** Log aktivitas hanya bisa dibaca, tidak bisa dibuat/diubah/dihapus dari dashboard. */
class ActivityLogPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_activity_log');
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->can('view_activity_log');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }
}
