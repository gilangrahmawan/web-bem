<?php

namespace Database\Seeders;

use App\Services\CalendarImportService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Kalender akademik UBHINUS + hari peringatan/hari jadi daerah 2026/2027
 * dari database/data/kalender-ubhinus-2026-2027.csv.
 *
 * INSERT-ONLY (keputusan pemilik): agenda dengan tanggal mulai + judul yang sama dilewati,
 * sehingga editan lewat dashboard tidak pernah ditimpa saat seeder dijalankan ulang.
 * Libur nasional & cuti bersama TIDAK ada di CSV ini — sumbernya hanya HolidaySeeder.
 *
 * Aman dijalankan di database produksi.
 */
class CalendarCsvSeeder extends Seeder
{
    public const FILE = 'data/kalender-ubhinus-2026-2027.csv';

    public function run(CalendarImportService $importer): void
    {
        $path = database_path(self::FILE);

        if (! is_file($path)) {
            throw new RuntimeException("File kalender tidak ditemukan: {$path}");
        }

        $rows = $importer->parse((string) file_get_contents($path));
        $invalid = array_filter($rows, fn (array $row): bool => $row['status'] === CalendarImportService::STATUS_INVALID);

        foreach ($invalid as $row) {
            $this->command?->warn("Baris {$row['line']} dilewati: ".implode(' ', $row['errors']));
        }

        $summary = $importer->import(null, $rows, basename($path));

        $this->command?->info("Kalender CSV: {$summary['created']} agenda baru, {$summary['duplicate']} sudah ada (dilewati), {$summary['invalid']} tidak valid.");
    }
}
