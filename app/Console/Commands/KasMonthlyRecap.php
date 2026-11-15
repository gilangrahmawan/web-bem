<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\DuePayment;
use App\Models\KasWeeklyPayment;
use App\Models\User;
use App\Notifications\KasMonthlyRecapNotification;
use App\Services\KasService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Rekap kas bulan sebelumnya ke Bendahara (dijadwalkan tanggal 1 pukul 08:00 WIB). */
#[Signature('kas:monthly-recap')]
#[Description('Kirim rekap kas & iuran bulan lalu ke Bendahara')]
class KasMonthlyRecap extends Command
{
    public function handle(KasService $kas): int
    {
        $from = now()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $totals = $kas->totals();

        $monthPayments = KasWeeklyPayment::query()->whereBetween('paid_at', [$from, $to]);

        $recap = [
            'month' => $from->locale('id')->translatedFormat('F Y'),
            'collected_month' => (int) (clone $monthPayments)->sum('amount'),
            'payments_month' => (clone $monthPayments)->count(),
            'dues_month' => (int) DuePayment::query()->whereBetween('paid_at', [$from, $to])->sum('amount'),
            'collected_total' => $totals['collected'],
            'arrears' => $totals['arrears'],
            'clear' => $totals['clear'],
            'payers' => $totals['payers'],
        ];

        $recipients = User::query()->active()->role(RoleName::Bendahara->value)->get();

        foreach ($recipients as $user) {
            $user->notify(new KasMonthlyRecapNotification($recap));
        }

        $this->info("Rekap {$recap['month']} terkirim ke {$recipients->count()} bendahara.");

        return self::SUCCESS;
    }
}
