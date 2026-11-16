<?php

namespace App\Console\Commands;

use App\Services\RecruitmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hapus CV, foto, nomor WA, dan email pendaftar oprec yang tidak diterima setelah masa simpan
 * (setting recruitment_retention_months, default 6 bulan sejak gelombang ditutup). Dijadwalkan harian.
 */
#[Signature('recruitment:purge-personal-data')]
#[Description('Hapus data pribadi pendaftar oprec yang tidak diterima setelah masa simpan')]
class PurgeRecruitmentData extends Command
{
    public function handle(RecruitmentService $service): int
    {
        $count = $service->purgePersonalData();

        $this->info("Data pribadi {$count} pendaftar dihapus.");

        return self::SUCCESS;
    }
}
