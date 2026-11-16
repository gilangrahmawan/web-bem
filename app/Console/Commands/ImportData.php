<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\Transfer\DataTransferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Import paket hasil bem:export-data (di produksi). Idempoten: aman dijalankan ulang.
 * --dry-run: tampilkan apa yang akan dibuat/diperbarui tanpa menyimpan apa pun.
 */
#[Signature('bem:import-data {path : Berkas ZIP hasil bem:export-data} {--dry-run : Hanya tampilkan rencana perubahan} {--force : Tanpa konfirmasi}')]
#[Description('Import data penting dari paket bem:export-data (tanpa akun & data demo)')]
class ImportData extends Command
{
    public function handle(DataTransferService $transfer, ActivityLogger $logger): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Import data ke database '.config('database.connections.'.config('database.default').'.database').'? Data yang cocok akan diperbarui.', false)) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        try {
            $report = $transfer->import($path, $dryRun);
        } catch (Throwable $e) {
            $this->error('Import gagal, tidak ada perubahan disimpan: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Data', 'Baru', 'Diperbarui'], collect($report)->map(fn ($r, $table) => [$table, $r['create'], $r['update']])->values()->all());

        if ($dryRun) {
            $this->warn('DRY RUN — tidak ada yang disimpan. Jalankan tanpa --dry-run untuk menyimpan.');
        } else {
            $logger->log('data.imported', null, ['file' => basename($path), 'report' => $report], null, recordIp: false);
            $this->info('Import selesai. Jalankan "php artisan optimize:clear" bila cache aktif.');
        }

        return self::SUCCESS;
    }
}
