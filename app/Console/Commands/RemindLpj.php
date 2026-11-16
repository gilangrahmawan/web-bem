<?php

namespace App\Console\Commands;

use App\Enums\ActivityStatus;
use App\Models\StudentActivity;
use App\Notifications\ActivityNotification;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Pengingat tenggat LPJ ke penanggung jawab (dijadwalkan harian 08:00 WIB):
 * H-3, hari tenggat, lalu setiap 7 hari selama terlambat. Kolom lpj_reminded_* mencegah kiriman dobel.
 */
#[Signature('activities:remind-lpj')]
#[Description('Kirim pengingat tenggat LPJ (H-3, hari tenggat, mingguan saat terlambat)')]
class RemindLpj extends Command
{
    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $sent = 0;

        StudentActivity::query()
            ->whereIn('status', [ActivityStatus::Approved->value, ActivityStatus::LpjRevision->value])
            ->with('pic')
            ->chunkById(100, function ($activities) use ($today, &$sent): void {
                foreach ($activities as $activity) {
                    if (! $activity->pic?->is_active) {
                        continue;
                    }

                    $due = $activity->lpjDueOn();
                    $daysLate = (int) $due->diffInDays($today, false);
                    $message = null;
                    $column = null;

                    if ($daysLate === -3 && $activity->lpj_reminded_h3_at === null) {
                        [$message, $column] = ['Tenggat LPJ 3 hari lagi', 'lpj_reminded_h3_at'];
                    } elseif ($daysLate === 0 && $activity->lpj_reminded_due_at === null) {
                        [$message, $column] = ['Hari ini tenggat LPJ', 'lpj_reminded_due_at'];
                    } elseif ($daysLate >= 7 && (
                        $activity->lpj_last_overdue_reminder_at === null
                        || CarbonImmutable::parse($activity->lpj_last_overdue_reminder_at)->startOfDay()->lessThanOrEqualTo($today->subDays(7))
                    )) {
                        [$message, $column] = ["LPJ terlambat {$daysLate} hari", 'lpj_last_overdue_reminder_at'];
                    }

                    if ($message === null) {
                        continue;
                    }

                    $activity->pic->notify(new ActivityNotification(
                        $activity,
                        $message,
                        "{$activity->name} — tenggat ".Format::date($due).'. Unggah LPJ di menu Kegiatan.',
                        'reminder',
                    ));
                    $activity->forceFill([$column => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Pengingat LPJ terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
