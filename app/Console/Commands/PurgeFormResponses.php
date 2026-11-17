<?php

namespace App\Console\Commands;

use App\Services\Forms\FormResponseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hapus jawaban form (termasuk berkas) setelah masa simpan per form (bawaan 12 bulan sejak ditutup;
 * "simpan selamanya" dilewati) dan draf jawaban yang tidak dikirim > 30 hari. Dijadwalkan harian.
 */
#[Signature('forms:purge-responses')]
#[Description('Hapus jawaban form yang melewati masa simpan dan draf jawaban terbengkalai')]
class PurgeFormResponses extends Command
{
    public function handle(FormResponseService $service): int
    {
        $result = $service->purgeExpired();

        $this->info("{$result['responses']} jawaban dan {$result['drafts']} draf jawaban dihapus.");

        return self::SUCCESS;
    }
}
