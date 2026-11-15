<?php

namespace App\Filament\Widgets;

use App\Enums\AnnouncementType;
use App\Filament\Pages\Inbox;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/** Kotak Masuk saya: pengumuman & surat belum dibaca, surat perlu tindak lanjut. Hanya milik user login. */
class InboxStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Kotak Masuk saya';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active;
    }

    protected function getStats(): array
    {
        $mine = fn (): Builder => AnnouncementRecipient::query()->where('user_id', Filament::auth()->id());
        $ofType = fn (Builder $q, AnnouncementType $t): Builder => $q->whereHas('announcement', fn (Builder $a) => $a->where('type', $t->value));

        $unreadAnnouncements = $ofType($mine()->whereNull('read_at'), AnnouncementType::Announcement)->count();
        $unreadLetters = $ofType($mine()->whereNull('read_at'), AnnouncementType::Letter)->count();
        $toFollowUp = $ofType($mine()->whereNull('followed_up_at'), AnnouncementType::Letter)->count();
        $url = Inbox::getUrl();

        return [
            Stat::make('Pengumuman belum dibaca', $unreadAnnouncements)
                ->icon(Heroicon::OutlinedMegaphone)->color($unreadAnnouncements ? 'warning' : 'gray')->url($url),
            Stat::make('Surat belum dibaca', $unreadLetters)
                ->icon(Heroicon::OutlinedEnvelope)->color($unreadLetters ? 'warning' : 'gray')->url($url),
            Stat::make('Surat perlu ditindaklanjuti', $toFollowUp)
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)->color($toFollowUp ? 'danger' : 'success')->url($url),
        ];
    }
}
