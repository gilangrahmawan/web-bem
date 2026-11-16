<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
| Otorisasi channel privat Pusher (Fase 9). Hanya anggota percakapan yang boleh subscribe —
| juga Super Admin (ConversationPolicy berlaku bagi super_admin). Pembina & user nonaktif ditolak.
*/

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId): bool {
    $conversation = Conversation::query()->find($conversationId);

    return $conversation !== null && Gate::forUser($user)->allows('view', $conversation);
});

Broadcast::channel('chat.user.{userId}', function (User $user, int $userId): bool {
    return (int) $user->getKey() === $userId && $user->canUseChat();
});
