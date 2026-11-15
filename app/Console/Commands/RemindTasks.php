<?php

namespace App\Console\Commands;

use App\Enums\EventCategory;
use App\Enums\TaskStatus;
use App\Models\EventAssignee;
use App\Notifications\TaskReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pengingat deadline tugas konten H-2 & H-1 (dijadwalkan harian 07:00 WIB di routes/console.php).
 * Kolom reminded_h2_at / reminded_h1_at mencegah pengingat terkirim dua kali.
 */
#[Signature('tasks:remind')]
#[Description('Kirim pengingat H-2 dan H-1 untuk tugas konten Medpub yang belum diserahkan')]
class RemindTasks extends Command
{
    private const STAGES = [
        2 => 'reminded_h2_at',
        1 => 'reminded_h1_at',
    ];

    public function handle(): int
    {
        $sent = 0;

        foreach (self::STAGES as $daysLeft => $column) {
            $day = now()->addDays($daysLeft);

            EventAssignee::query()
                ->whereIn('status', [TaskStatus::Todo->value, TaskStatus::InProgress->value])
                ->whereNull($column)
                ->whereHas('user', fn (Builder $q) => $q->where('is_active', true))
                ->whereHas('event', fn (Builder $q) => $q
                    ->where('category', EventCategory::Content->value)
                    ->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]))
                ->with(['event', 'user'])
                ->chunkById(100, function ($assignees) use ($daysLeft, $column, &$sent): void {
                    foreach ($assignees as $assignee) {
                        $assignee->user->notify(new TaskReminderNotification($assignee, $daysLeft));
                        $assignee->forceFill([$column => now()])->saveQuietly();
                        $sent++;
                    }
                });
        }

        $this->info("Pengingat terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
