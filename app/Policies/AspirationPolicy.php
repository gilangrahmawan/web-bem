<?php

namespace App\Policies;

use App\Models\Aspiration;
use App\Models\User;

/**
 * Aspirasi (AGENTS.md D3 + keputusan pemilik Fase 5).
 *
 * Lingkup lihat:
 * - Semua aspirasi: super_admin, Humas (aspiration.handle), Pembina (report.view), dan pemegang
 *   aspiration.view di luar divisi Kajian (Sekretaris).
 * - Hanya aspirasi yang sudah diteruskan ke Kajian: Koordinator Kajian (aspiration.study) dan
 *   Anggota Kajian (aspiration.view di divisi Kajian).
 * Identitas pengirim (nama & kontak) hanya untuk Humas & super_admin.
 * Status yang boleh diproses dicek di AspirationService.
 */
class AspirationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && (self::seesAll($user) || self::seesKajianStage($user));
    }

    public function view(User $user, Aspiration $aspiration): bool
    {
        return $this->viewAny($user)
            && Aspiration::query()->whereKey($aspiration->getKey())->visibleTo($user)->exists();
    }

    /** Tinjau, tanggapi, selesaikan, tolak (tahap Humas). */
    public function handle(User $user, ?Aspiration $aspiration = null): bool
    {
        return $user->can('aspiration.handle');
    }

    public function escalate(User $user, ?Aspiration $aspiration = null): bool
    {
        return $user->can('aspiration.escalate');
    }

    /** Teruskan eskalasi ke Kajian / tolak eskalasi. */
    public function forward(User $user, ?Aspiration $aspiration = null): bool
    {
        return $user->can('aspiration.forward_to_kajian');
    }

    public function study(User $user, ?Aspiration $aspiration = null): bool
    {
        return $user->can('aspiration.study');
    }

    public static function seesAll(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->checkPermissionTo('aspiration.handle')
            || $user->checkPermissionTo('report.view')
            || ($user->checkPermissionTo('aspiration.view') && ! self::inKajian($user));
    }

    public static function seesKajianStage(User $user): bool
    {
        return $user->checkPermissionTo('aspiration.study')
            || ($user->checkPermissionTo('aspiration.view') && self::inKajian($user));
    }

    /** Nama & kontak pengirim (keputusan pemilik): hanya Humas & super_admin. */
    public static function seesIdentity(User $user): bool
    {
        return $user->isSuperAdmin() || $user->checkPermissionTo('aspiration.handle');
    }

    private static function inKajian(User $user): bool
    {
        return $user->division?->slug === 'kajian';
    }
}
