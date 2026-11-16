<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Ke pengelola Drive: surat terkirim tetapi gagal diarsipkan ke folder "Surat". */
class DriveArchiveFailedNotification extends Notification
{
    public function __construct(
        public Announcement $letter,
        public string $reason,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
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
