<?php

namespace App\Policies;

use App\Models\Due;
use App\Models\User;

/**
 * Iuran tambahan (AGENTS.md D3: Kas & iuran).
 * - Lihat: kas.view_all (bendahara, super_admin, pembina) atau due.manage.
 * - Kelola & catat pembayaran: due.manage (bendahara; super_admin lewat Gate::before).
 * - Hapus hanya bila belum ada pembayaran (riwayat uang tidak boleh hilang diam-diam).
 * Pengurus biasa melihat iurannya sendiri lewat widget Kas Saya, bukan lewat resource ini.
 */
class DuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kas.view_all') || $user->can('due.manage');
    }

    public function view(User $user, Due $due): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('due.manage');
    }

    public function update(User $user, Due $due): bool
    {
        return $user->can('due.manage');
    }

    public function delete(User $user, Due $due): bool
    {
        return $user->can('due.manage') && ! $due->payments()->exists();
    }

    public function recordPayment(User $user, Due $due): bool
    {
        return $user->can('due.manage');
    }
}
