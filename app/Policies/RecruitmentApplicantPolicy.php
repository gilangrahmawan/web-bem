<?php

namespace App\Policies;

use App\Models\RecruitmentApplicant;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Pendaftar oprec (keputusan Fase 7B):
 * - recruitment.manage (Sekretaris, super_admin): semua pendaftar, ubah status, jadwal, keputusan, akun.
 * - recruitment.interview (Koordinator Kajian/Medpub/Humas): hanya pendaftar yang memilih divisinya; beri nilai.
 * - Pembina (report.view): lihat semua, read-only.
 * Pengaju kegiatan tidak memiliki izin apa pun di sini.
 */
class RecruitmentApplicantPolicy implements EnforcedForSuperAdmin
{
    /** Pendaftar hanya dari form publik, tidak pernah dihapus manual, dan tidak dinilai setelah final — juga bagi super_admin. */
    public static function superAdminEnforcedAbilities(): array
    {
        return ['create', 'delete', 'score'];
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active && (self::seesAll($user) || self::isInterviewer($user));
    }

    public function view(User $user, RecruitmentApplicant $applicant): bool
    {
        return $this->viewAny($user)
            && RecruitmentApplicant::query()->whereKey($applicant->getKey())->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return false; // Pendaftar hanya masuk dari form publik.
    }

    public function update(User $user, RecruitmentApplicant $applicant): bool
    {
        return $user->can('recruitment.manage');
    }

    public function delete(User $user, RecruitmentApplicant $applicant): bool
    {
        return false;
    }

    /** Beri nilai interview: pengelola oprec (semua) atau pewawancara divisi yang dipilih. */
    public function score(User $user, RecruitmentApplicant $applicant): bool
    {
        if (! $user->is_active || $applicant->status->isFinal()) {
            return false;
        }

        return $user->can('recruitment.manage')
            || (self::isInterviewer($user) && in_array($user->division_id, $applicant->choiceDivisionIds(), true));
    }

    public static function seesAll(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->checkPermissionTo('recruitment.manage')
            || $user->checkPermissionTo('report.view');
    }

    public static function isInterviewer(User $user): bool
    {
        return $user->checkPermissionTo('recruitment.interview') && $user->division_id !== null;
    }
}
