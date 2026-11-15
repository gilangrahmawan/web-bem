<?php

namespace App\Policies;

use App\Models\StructureMember;
use App\Models\User;

/** Data struktur kepengurusan dikelola Super Admin. */
class StructureMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_structure_member');
    }

    public function view(User $user, StructureMember $member): bool
    {
        return $user->can('view_structure_member');
    }

    public function create(User $user): bool
    {
        return $user->can('create_structure_member');
    }

    public function update(User $user, StructureMember $member): bool
    {
        return $user->can('update_structure_member');
    }

    public function delete(User $user, StructureMember $member): bool
    {
        return $user->can('delete_structure_member');
    }

    public function reorder(User $user): bool
    {
        return $user->can('update_structure_member');
    }
}
