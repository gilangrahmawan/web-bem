<?php

namespace App\Policies;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Pengumuman & surat (keputusan pemilik Fase 5):
 * - Daftar pengelolaan: pemegang announcement.create (pengumuman) / letter.create (surat); Pembina lihat semua.
 * - Tidak bisa diedit setelah terkirim — berlaku juga bagi super_admin (EnforcedForSuperAdmin).
 * - Hapus hanya super_admin (lolos lewat Gate::before; role lain selalu false).
 * - Penerima membaca lewat Kotak Masuk, bukan lewat resource ini.
 */
class AnnouncementPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['update'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can('announcement.create') || $user->can('letter.create') || $user->can('report.view');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->can('report.view')
            || $user->can($announcement->type->permission())
            || $announcement->created_by === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can('announcement.create') || $user->can('letter.create');
    }

    public function createType(User $user, AnnouncementType $type): bool
    {
        return $user->is_active && $user->can($type->permission());
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return false;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return false;
    }

    /** Lampiran: pengelola/pembuat/pembina, atau penerimanya. */
    public function downloadAttachment(User $user, Announcement $announcement): bool
    {
        return $this->view($user, $announcement)
            || $announcement->recipients()->where('user_id', $user->getKey())->exists();
    }
}
