<?php

namespace App\Policies;

use App\Models\EventAssignee;
use App\Models\User;

/**
 * Tugas konten (baris event_assignees). Cegah IDOR:
 * - status & hasil hanya diubah oleh penanggung jawabnya sendiri;
 * - review (Selesai / Kembalikan) oleh pengelola agenda (koordinator Medpub, super_admin);
 * - berkas hasil hanya bisa diunduh penanggung jawab, pengelola agenda, atau yang melihat semua agenda.
 */
class EventAssigneePolicy
{
    public function view(User $user, EventAssignee $assignee): bool
    {
        return $assignee->user_id === $user->getKey()
            || EventPolicy::seesAllEvents($user)
            || $this->review($user, $assignee);
    }

    /** Ubah status tugas milik sendiri / serahkan hasil. */
    public function update(User $user, EventAssignee $assignee): bool
    {
        return $user->is_active && $assignee->user_id === $user->getKey();
    }

    /** Tandai selesai / kembalikan ke "Dikerjakan". */
    public function review(User $user, EventAssignee $assignee): bool
    {
        return app(EventPolicy::class)->update($user, $assignee->event);
    }
}
