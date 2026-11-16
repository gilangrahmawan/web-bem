<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\Pages\Backups;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Ke Super Admin (lonceng + push, tanpa email — keputusan Fase 10):
 * backup gagal / tidak sehat / pembersihan gagal, dan pengingat bulanan mengunduh backup.
 */
class BackupProblemNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        public string $title,
        public string $body,
        public bool $warning = true,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body(Str::limit($this->body, 300))
            ->icon('heroicon-o-circle-stack')
            ->actions([
                Action::make('open')->label('Buka halaman Backup')->url(Backups::getUrl(panel: 'admin'))->markAsRead(),
            ]);

        $this->warning ? $notification->danger() : $notification->info();

        return $notification->getDatabaseMessage();
    }
}
