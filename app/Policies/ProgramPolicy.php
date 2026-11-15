<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

/**
 * Program kerja: super_admin semua divisi (lewat Gate::before);
 * pengurus lain yang boleh mengelola (koordinator) hanya divisinya sendiri (AGENTS.md D4).
 */
class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_program');
    }

    public function view(User $user, Program $program): bool
    {
        return $user->can('view_program');
    }

    public function create(User $user): bool
    {
        return $user->can('create_program') && $user->division_id !== null;
    }

    public function update(User $user, Program $program): bool
    {
        return $user->can('update_program') && $this->ownsDivision($user, $program);
    }

    public function delete(User $user, Program $program): bool
    {
        return $user->can('delete_program') && $this->ownsDivision($user, $program);
    }

    /**
     * Divisi yang membatasi pengelolaan program user ini.
     * null = tidak dibatasi (super_admin) atau user hanya bisa melihat.
     */
    public static function restrictedDivisionId(User $user): ?int
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $canManage = $user->checkPermissionTo('create_program')
            || $user->checkPermissionTo('update_program')
            || $user->checkPermissionTo('delete_program');

        return $canManage ? ($user->division_id ?? 0) : null;
    }

    private function ownsDivision(User $user, Program $program): bool
    {
        return $user->division_id !== null && $program->division_id === $user->division_id;
    }
}
