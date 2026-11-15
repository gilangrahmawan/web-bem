<?php

namespace App\Notifications;

use App\Models\Due;
use App\Models\User;
use App\Support\Format;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Terima kasih atas pembayaran/cicilan iuran tambahan, beserta sisa tagihan. */
class DuePaidNotification extends Notification
{
    public function __construct(public Due $due, public int $amount, public int $remaining) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $first = explode(' ', trim($notifiable->name))[0];
        $rest = $this->remaining > 0 ? ' Sisa tagihan: '.Format::rupiah($this->remaining).'.' : ' Iuran ini sudah lunas.';

        return FilamentNotification::make()
            ->title('Pembayaran iuran diterima')
            ->body("Terima kasih, {$first}! Pembayaran {$this->due->name} sebesar ".Format::rupiah($this->amount).' sudah kami terima.'.$rest)
            ->icon('heroicon-o-banknotes')
            ->success()
            ->getDatabaseMessage();
    }
}
