<?php

namespace App\Policies;

use App\Models\Discussion;
use App\Models\User;

/**
 * Pembahasan Kajian (AGENTS.md D3/D4).
 * - Semua pengurus aktif melihat (matriks: L untuk semua role, termasuk pembina).
 * - Tambah: discussion.create (Koordinator & Anggota Kajian).
 * - Ubah/hapus: discussion.manage_all (Koordinator) atau milik sendiri (Anggota).
 */
class DiscussionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Discussion $discussion): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->can('discussion.create');
    }

    public function update(User $user, Discussion $discussion): bool
    {
        return $this->manages($user, $discussion);
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $this->manages($user, $discussion);
    }

    public function setDocsLink(User $user): bool
    {
        return $user->can('discussion.set_docs_link');
    }

    private function manages(User $user, Discussion $discussion): bool
    {
        return $user->can('discussion.manage_all')
            || ($user->can('discussion.create') && $discussion->created_by === $user->getKey());
    }
}
