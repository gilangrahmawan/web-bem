<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\BackupProblemNotification;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Notification;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/** Kegagalan backup → lonceng + push ke semua Super Admin aktif, dan dicatat di activity log. */
class NotifyBackupProblems
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(BackupHasFailed|UnhealthyBackupWasFound|CleanupHasFailed $event): void
    {
        [$title, $body] = match (true) {
            $event instanceof BackupHasFailed => ['Backup GAGAL', 'Backup terakhir gagal dibuat: '.$event->exception->getMessage()],
            $event instanceof CleanupHasFailed => ['Pembersihan backup lama gagal', $event->exception->getMessage()],
            default => ['Backup tidak sehat', 'Backup terbaru terlalu lama atau penyimpanan backup melebihi batas. Periksa halaman Backup.'],
        };

        $this->logger->log('backup.problem', null, ['title' => $title, 'detail' => mb_substr($body, 0, 500)], recordIp: false);

        Notification::send(
            User::query()->activeSuperAdmins()->get(),
            new BackupProblemNotification($title, $body),
        );
    }
}
