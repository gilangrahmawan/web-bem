<?php

namespace App\Notifications;

use App\Filament\Resources\Aspirations\AspirationResource;
use App\Models\Aspiration;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Notifikasi alur aspirasi (baru, dieskalasi, diteruskan ke Kajian, hasil kajian). Tanpa identitas pengirim. */
class AspirationActivityNotification extends Notification
{
    public function __construct(public Aspiration $aspiration, public string $title, public string $body) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-megaphone')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Buka aspirasi')
                    ->url(AspirationResource::getUrl('view', ['record' => $this->aspiration], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
