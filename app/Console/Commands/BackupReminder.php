<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BackupProblemNotification;
use App\Services\BackupInventory;
use App\Support\Format;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat bulanan (tanggal 1) ke Super Admin: unduh backup terbaru ke tempat lain selama backup
 * masih disimpan di server yang sama (belum R2). Bila sudah memakai penyimpanan luar, tidak dikirim.
 */
#[Signature('bem:backup-reminder')]
#[Description('Ingatkan Super Admin mengunduh backup terbaru ke tempat lain')]
class BackupReminder extends Command
{
    public function handle(BackupInventory $inventory): int
    {
        if ($inventory->usesOffsiteStorage()) {
            $this->info('Backup sudah di penyimpanan luar; pengingat tidak dikirim.');

            return self::SUCCESS;
        }

        $latest = $inventory->files()->first();
        $body = $latest
            ? 'Backup terbaru: '.Format::date($latest['date']).' ('.Format::bytes($latest['size']).'). Unduh dari halaman Backup dan simpan di tempat lain (Google Drive akun BEM / flashdisk Sekretariat). Simpan juga APP_KEY & BACKUP_ARCHIVE_PASSWORD di tempat aman.'
            : 'Belum ada berkas backup di server. Periksa jadwal cron dan halaman Backup.';

        Notification::send(
            User::query()->activeSuperAdmins()->get(),
            new BackupProblemNotification('Pengingat bulanan: simpan salinan backup', $body, warning: $latest === null),
        );

        $this->info('Pengingat backup dikirim.');

        return self::SUCCESS;
    }
}
