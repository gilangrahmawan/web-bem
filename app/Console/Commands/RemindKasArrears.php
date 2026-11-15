<?php

namespace App\Console\Commands;

use App\Notifications\KasArrearsReminderNotification;
use App\Services\KasService;
use App\Support\Kas\KasMemberSummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pengingat tunggakan kas & iuran (dijadwalkan Senin 08:00 WIB).
 * Tidak dobel: user yang sudah menerima pengingat ini hari ini dilewati.
 */
#[Signature('kas:remind-arrears')]
#[Description('Kirim pengingat ke pengurus yang menunggak kas mingguan atau iuran tambahan')]
class RemindKasArrears extends Command
{
    public function handle(KasService $kas): int
    {
        if ($problem = $kas->setupProblem()) {
            $this->warn("Dilewati: {$problem}");

            return self::SUCCESS;
        }

        $sent = 0;

        $kas->memberSummaries()->each(function (KasMemberSummary $summary) use ($kas, &$sent): void {
            $user = $summary->user;
            $duesArrears = collect($kas->outstandingDuesFor($user))->sum('arrears');

            if ($summary->arrears === 0 && $duesArrears === 0) {
                return;
            }

            $alreadyToday = $user->notifications()
                ->where('type', KasArrearsReminderNotification::class)
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if ($alreadyToday) {
                return;
            }

            $user->notify(new KasArrearsReminderNotification(count($summary->unpaidWeeks), $summary->arrears, $duesArrears));
            $sent++;
        });

        $this->info("Pengingat tunggakan terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
