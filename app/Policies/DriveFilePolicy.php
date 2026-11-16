<?php

namespace App\Policies;

use App\Models\DriveFile;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;
use App\Services\Drive\DriveAccessResolver;
use App\Services\Drive\DriveGate;

/**
 * Hak akses file Drive — semua aturan di DriveGate (pewarisan izin folder).
 * Berlaku juga bagi super_admin (folder sistem terkunci; dokumen terkunci user lain), karena itu
 * Gate::before tidak dipakai untuk policy ini — super_admin tetap "manage" lewat DriveAccessResolver.
 */
class DriveFilePolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'share', 'edit'];
    }

    public function viewAny(User $user): bool
    {
        return app(DriveAccessResolver::class)->canUseDrive($user);
    }

    public function view(User $user, DriveFile $item): bool
    {
        return $this->gate()->view($user, $item);
    }

    public function create(User $user): bool
    {
        return false; // Selalu lewat Drive (DriveGate::createIn per folder).
    }

    /** Ganti nama / pindah. */
    public function update(User $user, DriveFile $item): bool
    {
        return $this->gate()->modify($user, $item);
    }

    /** Buang ke Sampah. */
    public function delete(User $user, DriveFile $item): bool
    {
        return ! $item->trashed() && $this->gate()->modify($user, $item);
    }

    public function restore(User $user, DriveFile $item): bool
    {
        return $item->trashed() && $this->gate()->restore($user, $item);
    }

    public function forceDelete(User $user, DriveFile $item): bool
    {
        return $item->trashed() && $this->gate()->forceDelete($user, $item);
    }

    private function gate(): DriveGate
    {
        return app(DriveGate::class);
    }
}
