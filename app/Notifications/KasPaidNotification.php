<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use App\Support\Format;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "Terima kasih, {nama}! Pembayaran kas minggu ke-{n} sebesar Rp5.000 sudah kami terima."
 * Beberapa minggu sekaligus → satu notifikasi dengan rentang minggu (keputusan pemilik).
 */
class KasPaidNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    /** @param  list<int>  $weeks */
    public function __construct(public array $weeks, public int $total) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::Kas;
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Pembayaran kas diterima')
            ->body($this->message($notifiable))
            ->icon('heroicon-o-banknotes')
            ->success()
            ->getDatabaseMessage();
    }

    public function message(User $notifiable): string
    {
        $first = explode(' ', trim($notifiable->name))[0];

        return "Terima kasih, {$first}! Pembayaran kas minggu ke-".self::weekList($this->weeks)
            .' sebesar '.Format::rupiah($this->total).' sudah kami terima.';
    }

    /** [3] → "3", [3,4,5,6] → "3–6", [3,5,6,7] → "3, 5–7". */
    public static function weekList(array $weeks): string
    {
        sort($weeks);
        $parts = [];
        $start = $prev = null;

        foreach ($weeks as $week) {
            if ($start === null) {
                $start = $prev = $week;
            } elseif ($week === $prev + 1) {
                $prev = $week;
            } else {
                $parts[] = $start === $prev ? (string) $start : "{$start}–{$prev}";
                $start = $prev = $week;
            }
        }

        if ($start !== null) {
            $parts[] = $start === $prev ? (string) $start : "{$start}–{$prev}";
        }

        return implode(', ', $parts);
    }
}
