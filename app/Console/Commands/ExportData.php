<?php

namespace App\Console\Commands;

use App\Services\Transfer\DataTransferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Export data penting (lokal) ke satu paket ZIP untuk dipindah ke produksi — tanpa akun & konten demo.
 * Paket berisi foto/berkas: simpan sebagai data pribadi, jangan dibagikan atau di-commit.
 */
#[Signature('bem:export-data {--output= : Lokasi berkas ZIP (default storage/app/private/exports/...)}')]
#[Description('Export pengaturan, struktur, agenda, organisasi, template, berita/kajian, galeri, unduhan (tanpa data demo)')]
class ExportData extends Command
{
    public function handle(DataTransferService $transfer): int
    {
        $target = $this->option('output') ?: storage_path('app/private/exports/bem-data-'.now()->format('Ymd-His').'.zip');

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0750, true);
        }

        $result = $transfer->export($target);

        $this->table(['Data', 'Jumlah'], collect($result['counts'])->map(fn ($n, $table) => [$table, $n])->values()->all());
        $this->info("Paket dibuat: {$result['path']}");
        $this->line('Di produksi: php artisan bem:import-data <berkas.zip> --dry-run (cek dulu), lalu tanpa --dry-run.');
        $this->warn('Paket berisi foto & berkas pengurus — simpan aman, jangan di-commit ke Git.');

        return self::SUCCESS;
    }
}
