<?php

namespace App\Notifications;

use App\Enums\ConversationType;
use App\Enums\NotificationCategory;
use App\Filament\Pages\Chat;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Pesan chat baru → HANYA Web Push (tidak masuk lonceng; jumlah belum dibaca tampil di menu Chat).
 * Tidak dikirim untuk percakapan yang di-mute (disaring ChatService) atau bila jenis Chat dimatikan.
 * Isi pesan dienkripsi end-to-end oleh protokol Web Push; server push tidak bisa membacanya.
 */
class ChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(public Message $message) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable, []);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::Chat;
    }

    /** @return array<string, mixed> */
    public function pushPayload(User $notifiable): array
    {
        $message = $this->message->loadMissing(['sender', 'conversation']);
        $conversation = $message->conversation;
        $sender = $message->sender?->name ?? 'Seseorang';

        return [
            'title' => $conversation->type === ConversationType::Personal ? $sender : $conversation->name,
            'body' => ($conversation->type === ConversationType::Personal ? '' : $sender.': ').$message->preview(160),
            'url' => Chat::getUrl(['percakapan' => $conversation->getKey()], panel: 'admin'),
            'tag' => 'chat-'.$conversation->getKey(),
        ];
    }
}
