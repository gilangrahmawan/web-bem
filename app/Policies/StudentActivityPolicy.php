<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Enums\RoleName;
use App\Models\StudentActivity;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Kegiatan (keputusan Fase 7).
 * - Lihat semua: super_admin, Sekretaris (submission.verify), Bendahara (submission.review_budget),
 *   Pembina (report.view). Pengaju: kegiatan organisasinya. Pengurus lain: yang ia buat/pegang;
 *   koordinator divisi: kegiatan internal BEM dengan divisi pelaksana = divisinya (read-only).
 * - Edit draft, ajukan proposal, unggah LPJ: hanya penanggung jawab (juga berlaku bagi super_admin).
 * - Review per tahap: Sekretaris → Bendahara → Ketua/Wakil; super_admin boleh mengambil alih tahap
 *   Sekretaris/Bendahara; penyetuju tidak boleh penanggung jawab atau pembuat kegiatan.
 */
class StudentActivityPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['update', 'submitProposal', 'submitLpj', 'review', 'requestPicTransfer'];
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && ($user->can('activity.submit') || self::seesAll($user) || self::isDivisionCoordinator($user));
    }

    public function view(User $user, StudentActivity $activity): bool
    {
        return $this->viewAny($user)
            && StudentActivity::query()->whereKey($activity->getKey())->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->can('activity.submit')
            && (! $user->isApplicant() || (bool) $user->organization?->is_active);
    }

    public function update(User $user, StudentActivity $activity): bool
    {
        return $this->isPic($user, $activity) && $activity->status->isEditableByPic();
    }

    public function submitProposal(User $user, StudentActivity $activity): bool
    {
        return $this->isPic($user, $activity) && $activity->status->isEditableByPic();
    }

    public function submitLpj(User $user, StudentActivity $activity): bool
    {
        return $this->isPic($user, $activity) && $activity->status->awaitsLpj();
    }

    /** Batalkan: penanggung jawab (sebelum final) atau super_admin (lolos Gate::before). */
    public function cancel(User $user, StudentActivity $activity): bool
    {
        return $this->isPic($user, $activity) && ! $activity->status->isFinal();
    }

    /** Memproses tahap review saat ini. */
    public function review(User $user, StudentActivity $activity): bool
    {
        if (! $user->is_active || $this->isInvolved($user, $activity)) {
            return false;
        }

        return match ($activity->status->reviewStep()) {
            'secretary' => $user->isSuperAdmin() || $user->checkPermissionTo('submission.verify'),
            'treasurer' => $user->isSuperAdmin() || $user->checkPermissionTo('submission.review_budget'),
            'leader' => $user->hasRole(RoleName::SuperAdmin->value),
            default => false,
        };
    }

    /** Ajukan pindah penanggung jawab: penanggung jawab lama atau Sekretaris. */
    public function requestPicTransfer(User $user, StudentActivity $activity): bool
    {
        return $user->is_active && ! in_array($activity->status, [ActivityStatus::Completed, ActivityStatus::Rejected, ActivityStatus::Cancelled], true)
            && ($this->isPic($user, $activity) || $user->isSuperAdmin() || $user->checkPermissionTo('submission.verify'));
    }

    /** Setujui/tolak pindah penanggung jawab: Sekretaris (atau super_admin). */
    public function decidePicTransfer(User $user, StudentActivity $activity): bool
    {
        return $user->is_active && $user->can('submission.verify');
    }

    public static function seesAll(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->checkPermissionTo('submission.verify')
            || $user->checkPermissionTo('submission.review_budget')
            || $user->checkPermissionTo('submission.decide')
            || $user->checkPermissionTo('report.view');
    }

    public static function isDivisionCoordinator(User $user): bool
    {
        return $user->division_id !== null && $user->hasAnyRole([
            RoleName::KoordinatorKajian->value,
            RoleName::KoordinatorMedpub->value,
            RoleName::KoordinatorHumas->value,
        ]);
    }

    private function isPic(User $user, StudentActivity $activity): bool
    {
        return $user->is_active && $activity->pic_user_id === $user->getKey();
    }

    /** Penanggung jawab atau pembuat kegiatan tidak boleh menyetujui tahap mana pun. */
    private function isInvolved(User $user, StudentActivity $activity): bool
    {
        return in_array($user->getKey(), array_filter([$activity->pic_user_id, $activity->created_by]), true);
    }
}
