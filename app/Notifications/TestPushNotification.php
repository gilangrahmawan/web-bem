<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\Pages\NotificationSettings;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;

/** Tombol "Kirim notifikasi uji" — langsung (tanpa antrean), hanya Web Push. */
class TestPushNotification extends Notification
{
    use SendsWebPush;

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable, []);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    /** @return array<string, mixed> */
    public function pushPayload(User $notifiable): array
    {
        return [
            'title' => 'Notifikasi aktif ✔',
            'body' => 'Halo '.$notifiable->name.', notifikasi BEM UBHINUS sudah berjalan di perangkat ini.',
            'url' => NotificationSettings::getUrl(panel: 'admin'),
            'tag' => 'test',
        ];
    }
}
