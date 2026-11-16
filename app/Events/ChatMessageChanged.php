<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sinyal real-time "ada pesan baru/dihapus" (Pusher, langsung tanpa antrean).
 * TIDAK membawa isi pesan — browser mengambil isi lewat Livewire setelah lolos policy,
 * sehingga isi chat tidak pernah melewati server Pusher.
 * Channel: conversation.{id} (percakapan yang sedang dibuka) + chat.user.{id} tiap anggota (badge & daftar).
 */
class ChatMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param  list<int>  $memberIds */
    public function __construct(
        public int $conversationId,
        public int $messageId,
        public string $kind,
        public array $memberIds,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversationId),
            ...array_map(fn (int $id) => new PrivateChannel('chat.user.'.$id), $this->memberIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.changed';
    }

    /** @return array<string, int|string> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'kind' => $this->kind,
        ];
    }
}
