<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Form;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Form (Fase 11A, keputusan pemilik):
 * - Super Admin & Sekretaris: kelola SEMUA form.
 * - Koordinator divisi (form.manage): kelola form divisinya saja.
 * - Pembina (report.view): hanya lihat form & jawaban (tanpa ubah, tanpa export).
 * - Anggota divisi, Bendahara, pengaju: tidak punya akses.
 */
class FormPolicy implements EnforcedForSuperAdmin
{
    /** Form yang sudah punya jawaban tidak bisa dihapus — juga oleh Super Admin. */
    public static function superAdminEnforcedAbilities(): array
    {
        return ['delete'];
    }

    /** Kelola semua form. */
    public static function managesAll(User $user): bool
    {
        return $user->is_active && ($user->isSuperAdmin() || ($user->hasRole(RoleName::Sekretaris->value) && $user->can('form.manage')));
    }

    /** Lihat semua form (pengelola semua + Pembina). */
    public static function seesAll(User $user): bool
    {
        return self::managesAll($user) || ($user->is_active && $user->hasRole(RoleName::Pembina->value) && $user->can('report.view'));
    }

    /** Koordinator divisi: form.manage terbatas divisinya. */
    public static function isDivisionManager(User $user): bool
    {
        return $user->is_active && ! self::managesAll($user) && $user->can('form.manage') && $user->division_id !== null && ! $user->isApplicant();
    }

    public function viewAny(User $user): bool
    {
        return self::seesAll($user) || self::isDivisionManager($user);
    }

    public function view(User $user, Form $form): bool
    {
        return self::seesAll($user) || (self::isDivisionManager($user) && $form->division_id === $user->division_id);
    }

    public function create(User $user): bool
    {
        return self::managesAll($user) || self::isDivisionManager($user);
    }

    public function update(User $user, Form $form): bool
    {
        return self::managesAll($user) || (self::isDivisionManager($user) && $form->division_id === $user->division_id);
    }

    /** Hapus hanya bila belum ada jawaban terkirim. */
    public function delete(User $user, Form $form): bool
    {
        return $this->update($user, $form) && ! $form->hasSubmittedResponses();
    }

    /** Lihat ringkasan & jawaban. */
    public function viewResponses(User $user, Form $form): bool
    {
        return $this->view($user, $form);
    }

    /** Export & nilai esai: pengelola saja (bukan Pembina). */
    public function export(User $user, Form $form): bool
    {
        return $this->update($user, $form);
    }

    public function grade(User $user, Form $form): bool
    {
        return $this->update($user, $form);
    }
}
