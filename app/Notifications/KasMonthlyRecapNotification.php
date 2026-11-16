<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\Pages\KasWeekly;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Rekap bulanan kas ke Bendahara (command kas:monthly-recap, tanggal 1 pukul 08:00 WIB). */
class KasMonthlyRecapNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    /**
     * @param  array{month: string, collected_month: int, payments_month: int, dues_month: int,
     *     collected_total: int, arrears: int, clear: int, payers: int}  $recap
     */
    public function __construct(public array $recap) {}

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
        $r = $this->recap;

        return FilamentNotification::make()
            ->title("Rekap kas {$r['month']}")
            ->body('Kas masuk bulan ini: '.Format::rupiah($r['collected_month'])." ({$r['payments_month']} minggu). "
                .'Iuran tambahan: '.Format::rupiah($r['dues_month']).'. '
                .'Total kas periode: '.Format::rupiah($r['collected_total']).'. '
                .'Tunggakan saat ini: '.Format::rupiah($r['arrears'])." · Lunas {$r['clear']}/{$r['payers']} pengurus.")
            ->icon('heroicon-o-chart-bar')
            ->info()
            ->actions([
                Action::make('open')->label('Buka Kas Mingguan')->url(KasWeekly::getUrl(panel: 'admin'))->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
