<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Ke pengelola Drive: surat terkirim tetapi gagal diarsipkan ke folder "Surat". */
class DriveArchiveFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        public Announcement $letter,
        public string $reason,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::Drive;
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Arsip surat ke Drive gagal')
            ->body('Surat "'.Str::limit($this->letter->title, 80).'" tetap terkirim, tetapi lampirannya belum masuk folder Surat. '
                .'Penyebab: '.Str::limit($this->reason, 160))
            ->icon('heroicon-o-exclamation-triangle')
            ->warning()
            ->getDatabaseMessage();
    }
}
