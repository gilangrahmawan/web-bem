<?php

namespace App\Console\Commands;

use App\Services\Drive\DriveService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Hapus permanen isi Sampah Drive yang lebih tua dari drive_trash_days (default 30 hari). Dijadwalkan harian. */
#[Signature('drive:purge-trash')]
#[Description('Hapus permanen isi Sampah Drive yang sudah lewat masa simpan')]
class PurgeDriveTrash extends Command
{
    public function handle(DriveService $drive): int
    {
        $count = $drive->purgeTrash();

        $this->info("{$count} item Sampah Drive dihapus permanen.");

        return self::SUCCESS;
    }
}
