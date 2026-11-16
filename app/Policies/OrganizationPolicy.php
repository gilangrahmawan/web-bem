<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/** Organisasi pengaju: dikelola Super Admin & Sekretaris (applicant.manage). Tidak dihapus — dinonaktifkan. */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('applicant.manage');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('applicant.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('applicant.manage');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can('applicant.manage');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }
}
