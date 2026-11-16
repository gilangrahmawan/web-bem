<?php

namespace App\Policies;

use App\Models\Recruitment;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/** Gelombang oprec: kelola = recruitment.manage (Sekretaris, super_admin); lihat juga Pembina (report.view). */
class RecruitmentPolicy implements EnforcedForSuperAdmin
{
    /** Gelombang berisi pendaftar tidak bisa dihapus; hasil hanya diumumkan sekali — juga bagi super_admin. */
    public static function superAdminEnforcedAbilities(): array
    {
        return ['delete', 'publishResults'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.manage') || $user->can('report.view');
    }

    public function view(User $user, Recruitment $recruitment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('recruitment.manage');
    }

    public function update(User $user, Recruitment $recruitment): bool
    {
        return $user->can('recruitment.manage');
    }

    public function delete(User $user, Recruitment $recruitment): bool
    {
        return $user->can('recruitment.manage') && ! $recruitment->applicants()->exists();
    }

    public function publishResults(User $user, Recruitment $recruitment): bool
    {
        return $user->can('recruitment.manage') && ! $recruitment->resultsPublished();
    }
}
