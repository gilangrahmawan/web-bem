<?php

namespace App\Filament\Widgets;

use App\Enums\ConversationType;
use App\Filament\Pages\Chat;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatService;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan chat milik user sendiri (tanpa isi pesan). Semua pemakai chat; bukan Pembina. */
class ChatStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 70;

    protected ?string $heading = 'Chat';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canUseChat();
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $unread = app(ChatService::class)->unreadCount($user);
        $mine = Conversation::query()->forUser($user);
        $active = (clone $mine)->where('last_message_at', '>=', now()->subDays(7))->count();
        $sent = Message::query()->where('user_id', $user->getKey())->where('created_at', '>=', now()->subDays(7))->count();
        $url = Chat::getUrl();

        return [
            Stat::make('Pesan belum dibaca', $unread)
                ->descriptionIcon(Heroicon::OutlinedEnvelope)->description($unread > 0 ? 'Buka Chat untuk membaca' : 'Semua sudah dibaca')
                ->color($unread > 0 ? 'danger' : 'success')->url($url),
            Stat::make('Percakapan aktif (7 hari)', $active)
                ->description((clone $mine)->where('type', '!=', ConversationType::Personal->value)->count().' grup diikuti')
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)->url($url),
            Stat::make('Pesan terkirim (7 hari)', $sent)->descriptionIcon(Heroicon::OutlinedPaperAirplane)->url($url),
        ];
    }
}
