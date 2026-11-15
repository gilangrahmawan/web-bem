<?php

namespace App\Notifications;

use App\Enums\AnnouncementType;
use App\Filament\Pages\Inbox;
use App\Models\Announcement;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Pengumuman / surat baru di Kotak Masuk. */
class AnnouncementReceivedNotification extends Notification
{
    public function __construct(public Announcement $announcement) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $isLetter = $this->announcement->type === AnnouncementType::Letter;

        return FilamentNotification::make()
            ->title($isLetter ? 'Surat baru' : 'Pengumuman baru')
            ->body($this->announcement->title)
            ->icon($isLetter ? 'heroicon-o-envelope' : 'heroicon-o-megaphone')
            ->info()
            ->actions([
                Action::make('open')->label('Buka Kotak Masuk')->url(Inbox::getUrl(panel: 'admin'))->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
