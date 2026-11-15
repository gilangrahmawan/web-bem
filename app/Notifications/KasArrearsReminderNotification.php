<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Format;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Pengingat tunggakan kas/iuran (command kas:remind-arrears, Senin 08:00 WIB). */
class KasArrearsReminderNotification extends Notification
{
    public function __construct(
        public int $unpaidWeeks,
        public int $kasArrears,
        public int $duesArrears,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $parts = [];

        if ($this->kasArrears > 0) {
            $parts[] = "kas {$this->unpaidWeeks} minggu (".Format::rupiah($this->kasArrears).')';
        }

        if ($this->duesArrears > 0) {
            $parts[] = 'iuran tambahan '.Format::rupiah($this->duesArrears);
        }

        return FilamentNotification::make()
            ->title('Pengingat tunggakan kas')
            ->body('Halo! Masih ada tunggakan '.implode(' dan ', $parts).'. Silakan hubungi Bendahara untuk pembayaran.')
            ->icon('heroicon-o-exclamation-circle')
            ->warning()
            ->getDatabaseMessage();
    }
}
