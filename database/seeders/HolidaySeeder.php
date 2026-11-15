<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * Hari libur nasional 2026 & 2027 (data manual, tanpa API eksternal). Kategori `holiday`, tampil publik.
 *
 * ⚠ PERLU DIVERIFIKASI: tanggal libur KEAGAMAAN (ditandai 'verify' => true) diisi dari perkiraan
 * kalender hijriah/lunar, bukan dari SKB 3 Menteri resmi. Cocokkan dengan SKB libur nasional &
 * cuti bersama tahun bersangkutan, lalu koreksi lewat Kalender di dashboard. Cuti bersama tidak dimasukkan.
 *
 * Aman dijalankan berulang (juga di database produksi): agenda dicari berdasarkan judul,
 * jadi tanggal yang sudah dikoreksi di dashboard TIDAK ditimpa.
 */
class HolidaySeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2?: bool}> [tanggal, judul, verify] */
    private const HOLIDAYS = [
        // 2026
        ['2026-01-01', 'Tahun Baru 2026 Masehi'],
        ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW 1447 H', true],
        ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili', true],
        ['2026-03-19', 'Hari Suci Nyepi Tahun Baru Saka 1948', true],
        ['2026-03-21', 'Hari Raya Idul Fitri 1447 H (hari pertama)', true],
        ['2026-03-22', 'Hari Raya Idul Fitri 1447 H (hari kedua)', true],
        ['2026-04-03', 'Wafat Yesus Kristus 2026', true],
        ['2026-04-05', 'Kebangkitan Yesus Kristus (Paskah) 2026', true],
        ['2026-05-01', 'Hari Buruh Internasional 2026'],
        ['2026-05-14', 'Kenaikan Yesus Kristus 2026', true],
        ['2026-05-27', 'Hari Raya Idul Adha 1447 H', true],
        ['2026-05-31', 'Hari Raya Waisak 2570 BE', true],
        ['2026-06-01', 'Hari Lahir Pancasila 2026'],
        ['2026-06-16', 'Tahun Baru Islam 1448 H', true],
        ['2026-08-17', 'Hari Kemerdekaan Republik Indonesia ke-81'],
        ['2026-08-25', 'Maulid Nabi Muhammad SAW 1448 H', true],
        ['2026-12-25', 'Hari Raya Natal 2026'],

        // 2027 — tanggal keagamaan berbasis perkiraan; WAJIB dicek dengan SKB 2027.
        ['2027-01-01', 'Tahun Baru 2027 Masehi'],
        ['2027-01-06', 'Isra Mikraj Nabi Muhammad SAW 1448 H', true],
        ['2027-02-06', 'Tahun Baru Imlek 2578 Kongzili', true],
        ['2027-03-08', 'Hari Suci Nyepi Tahun Baru Saka 1949', true],
        ['2027-03-10', 'Hari Raya Idul Fitri 1448 H (hari pertama)', true],
        ['2027-03-11', 'Hari Raya Idul Fitri 1448 H (hari kedua)', true],
        ['2027-03-26', 'Wafat Yesus Kristus 2027', true],
        ['2027-03-28', 'Kebangkitan Yesus Kristus (Paskah) 2027', true],
        ['2027-05-01', 'Hari Buruh Internasional 2027'],
        ['2027-05-06', 'Kenaikan Yesus Kristus 2027', true],
        ['2027-05-17', 'Hari Raya Idul Adha 1448 H', true],
        ['2027-05-20', 'Hari Raya Waisak 2571 BE', true],
        ['2027-06-01', 'Hari Lahir Pancasila 2027'],
        ['2027-06-06', 'Tahun Baru Islam 1449 H', true],
        ['2027-08-15', 'Maulid Nabi Muhammad SAW 1449 H', true],
        ['2027-08-17', 'Hari Kemerdekaan Republik Indonesia ke-82'],
        ['2027-12-25', 'Hari Raya Natal 2027'],
    ];

    public function run(): void
    {
        $created = 0;
        $toVerify = 0;

        foreach (self::HOLIDAYS as $row) {
            [$date, $title] = $row;

            $event = Event::query()->firstOrCreate(
                ['category' => EventCategory::Holiday->value, 'title' => $title],
                [
                    'visibility' => EventCategory::Holiday->visibility()->value,
                    'starts_at' => $date.' 00:00:00',
                    'all_day' => true,
                    'organizer' => 'Pemerintah RI',
                ],
            );

            $created += $event->wasRecentlyCreated ? 1 : 0;
            $toVerify += ($row[2] ?? false) ? 1 : 0;
        }

        $this->command?->info("Hari libur: {$created} agenda baru (".count(self::HOLIDAYS).' total di data).');
        $this->command?->warn("{$toVerify} tanggal libur keagamaan perlu diverifikasi dengan SKB 3 Menteri.");
    }
}
