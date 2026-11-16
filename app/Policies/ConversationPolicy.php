<?php

namespace App\Policies;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Policies\Contracts\EnforcedForSuperAdmin;

/**
 * Chat (AGENTS.md C6, D4). SEMUA aturan berlaku juga untuk super_admin (Gate::before tidak dipakai):
 * hanya ANGGOTA percakapan yang bisa membaca, mengirim, dan subscribe channel-nya — Super Admin tidak
 * bisa membuka chat personal orang lain. Pembina tidak punya akses chat sama sekali.
 * Super Admin hanya boleh MEMBUBARKAN grup buatan pengguna (tanpa membaca isinya).
 */
class ConversationPolicy implements EnforcedForSuperAdmin
{
    public static function superAdminEnforcedAbilities(): array
    {
        return ['viewAny', 'view', 'send', 'manage', 'leave', 'dissolve', 'deleteMessage'];
    }

    public function viewAny(User $user): bool
    {
        return $user->canUseChat();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->canUseChat() && $conversation->hasMember($user);
    }

    /** Kirim pesan: anggota; percakapan personal hanya bila lawan bicara masih bisa memakai chat. */
    public function send(User $user, Conversation $conversation): bool
    {
        if (! $this->view($user, $conversation)) {
            return false;
        }

        if ($conversation->type === ConversationType::Personal) {
            return (bool) $conversation->otherUser($user)?->canUseChat();
        }

        return true;
    }

    /** Ganti nama, tambah/keluarkan anggota: admin grup buatan pengguna. */
    public function manage(User $user, Conversation $conversation): bool
    {
        return $conversation->type === ConversationType::Custom
            && $user->canUseChat()
            && $conversation->members()->where('user_id', $user->getKey())->where('role', ConversationMember::ADMIN)->exists();
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $conversation->type === ConversationType::Custom && $this->view($user, $conversation);
    }

    /** Bubarkan: admin grup, atau Super Admin (grup buatan pengguna saja; grup otomatis tidak bisa). */
    public function dissolve(User $user, Conversation $conversation): bool
    {
        if ($conversation->type !== ConversationType::Custom || ! $user->canUseChat()) {
            return false;
        }

        return $user->isSuperAdmin() || $this->manage($user, $conversation);
    }

    /** Dipanggil: Gate::allows('deleteMessage', [$conversation, $message]). Hanya pesan milik sendiri. */
    public function deleteMessage(User $user, Conversation $conversation, Message $message): bool
    {
        return ! $message->trashed()
            && $message->conversation_id === $conversation->getKey()
            && $message->user_id === $user->getKey()
            && $this->view($user, $conversation);
    }
}
