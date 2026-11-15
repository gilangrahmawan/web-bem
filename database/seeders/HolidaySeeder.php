<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Models\Event;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Libur nasional & cuti bersama 2026–2027 sesuai SKB 3 Menteri. Data manual, tanpa API eksternal.
 * Kategori `holiday`, tampil publik. Satu-satunya sumber libur nasional & cuti bersama di aplikasi.
 *
 * Sumber:
 * - 2026: SKB 3 Menteri Nomor 1497/2/5 Tahun 2025 (17 libur nasional + 8 cuti bersama = 25)
 * - 2027: SKB 3 Menteri Nomor 1205/3/2 Tahun 2026 (18 libur nasional + 8 cuti bersama = 26)
 * Cuti bersama memakai judul berakhiran "(Cuti Bersama)".
 *
 * Catatan: tanggal Idulfitri dan Iduladha (beserta cuti bersamanya) bisa bergeser mengikuti
 * hasil sidang isbat Kementerian Agama. Bila bergeser, perbarui tanggal di daftar ini lalu jalankan ulang.
 * Isra Mikraj memang muncul dua kali di 2027 (1448 H pada Januari dan 1449 H pada Desember).
 *
 * MODE SINKRON (keputusan pemilik): daftar HOLIDAYS adalah sumber kebenaran untuk libur milik seeder.
 * - Upsert berdasarkan TANGGAL + JUDUL (judul saja tidak unik, mis. "Idulfitri 1447 H" dua hari).
 *   Deskripsi & lokasi yang ditambahkan pengurus tidak disentuh.
 * - Libur milik seeder yang tidak ada di daftar (mis. hasil perkiraan lama) DIHAPUS.
 * - Cakupan HANYA agenda kategori `holiday` yang dibuat seeder (created_by = null). Agenda kategori lain
 *   (commemoration, campus, bem, ukm, meeting, content, internal) dan libur yang ditambahkan manual
 *   lewat dashboard tidak pernah disentuh.
 */
class HolidaySeeder extends Seeder
{
    private const COLLECTIVE_LEAVE = ' (Cuti Bersama)';

    /** @var list<array{0: string, 1: string}> [tanggal, judul] */
    public const HOLIDAYS = [
        // Libur nasional 2026 — SKB 1497/2/5 Tahun 2025
        ['2026-01-01', 'Tahun Baru 2026 Masehi'],
        ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW'],
        ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili'],
        ['2026-03-19', 'Hari Suci Nyepi (Tahun Baru Saka 1948)'],
        ['2026-03-21', 'Idulfitri 1447 H'],
        ['2026-03-22', 'Idulfitri 1447 H'],
        ['2026-04-03', 'Wafat Yesus Kristus'],
        ['2026-04-05', 'Kebangkitan Yesus Kristus (Paskah)'],
        ['2026-05-01', 'Hari Buruh Internasional'],
        ['2026-05-14', 'Kenaikan Yesus Kristus'],
        ['2026-05-27', 'Iduladha 1447 H'],
        ['2026-05-31', 'Hari Raya Waisak 2570 BE'],
        ['2026-06-01', 'Hari Lahir Pancasila'],
        ['2026-06-16', 'Tahun Baru Islam 1448 H'],
        ['2026-08-17', 'Proklamasi Kemerdekaan RI'],
        ['2026-08-25', 'Maulid Nabi Muhammad SAW'],
        ['2026-12-25', 'Kelahiran Yesus Kristus'],

        // Cuti bersama 2026
        ['2026-02-16', 'Tahun Baru Imlek'.self::COLLECTIVE_LEAVE],
        ['2026-03-18', 'Hari Suci Nyepi'.self::COLLECTIVE_LEAVE],
        ['2026-03-20', 'Idulfitri'.self::COLLECTIVE_LEAVE],
        ['2026-03-23', 'Idulfitri'.self::COLLECTIVE_LEAVE],
        ['2026-03-24', 'Idulfitri'.self::COLLECTIVE_LEAVE],
        ['2026-05-15', 'Kenaikan Yesus Kristus'.self::COLLECTIVE_LEAVE],
        ['2026-05-28', 'Iduladha'.self::COLLECTIVE_LEAVE],
        ['2026-12-24', 'Kelahiran Yesus Kristus'.self::COLLECTIVE_LEAVE],

        // Libur nasional 2027 — SKB 1205/3/2 Tahun 2026
        ['2027-01-01', 'Tahun Baru 2027 Masehi'],
        ['2027-01-05', 'Isra Mikraj Nabi Muhammad SAW 1448 H'],
        ['2027-02-06', 'Tahun Baru Imlek 2578 Kongzili'],
        ['2027-03-08', 'Hari Suci Nyepi (Tahun Baru Saka 1949)'],
        ['2027-03-10', 'Idulfitri 1448 H'],
        ['2027-03-11', 'Idulfitri 1448 H'],
        ['2027-03-26', 'Wafat Yesus Kristus'],
        ['2027-03-28', 'Kebangkitan Yesus Kristus (Paskah)'],
        ['2027-05-01', 'Hari Buruh Internasional'],
        ['2027-05-06', 'Kenaikan Yesus Kristus'],
        ['2027-05-17', 'Iduladha 1448 H'],
        ['2027-05-20', 'Hari Raya Waisak 2571 BE'],
        ['2027-06-01', 'Hari Lahir Pancasila'],
        ['2027-06-06', 'Tahun Baru Islam 1449 H'],
        ['2027-08-15', 'Maulid Nabi Muhammad SAW'],
        ['2027-08-17', 'Proklamasi Kemerdekaan RI'],
        ['2027-12-25', 'Kelahiran Yesus Kristus'],
        ['2027-12-26', 'Isra Mikraj Nabi Muhammad SAW 1449 H'],

        // Cuti bersama 2027
        ['2027-02-05', 'Tahun Baru Imlek'.self::COLLECTIVE_LEAVE],
        ['2027-03-09', 'Idulfitri'.self::COLLECTIVE_LEAVE],
        ['2027-03-12', 'Idulfitri'.self::COLLECTIVE_LEAVE],
        ['2027-03-15', 'Idulfitri'.self::COLLECTIVE_LEAVE],
        ['2027-03-25', 'Wafat Yesus Kristus'.self::COLLECTIVE_LEAVE],
        ['2027-05-18', 'Iduladha'.self::COLLECTIVE_LEAVE],
        ['2027-05-19', 'Hari Raya Waisak'.self::COLLECTIVE_LEAVE],
        ['2027-12-24', 'Kelahiran Yesus Kristus'.self::COLLECTIVE_LEAVE],
    ];

    public function run(ActivityLogger $logger): void
    {
        $summary = DB::transaction(function (): array {
            $keptIds = [];
            $created = 0;
            $updated = 0;

            foreach (self::HOLIDAYS as $row) {
                [$date, $title] = $row;

                $day = CarbonImmutable::parse($date);

                $event = self::seederHolidays()
                    ->where('title', $title)
                    ->whereBetween('starts_at', [$day->startOfDay(), $day->endOfDay()])
                    ->whereNotIn('id', $keptIds)
                    ->orderBy('id')
                    ->first()
                    ?? new Event(['category' => EventCategory::Holiday, 'title' => $title]);

                $event->fill([
                    'visibility' => EventCategory::Holiday->visibility(),
                    'starts_at' => $day->startOfDay(),
                    'ends_at' => null,
                    'all_day' => true,
                    'organizer' => 'Pemerintah RI',
                ]);

                if (! $event->exists) {
                    $created++;
                } elseif ($event->isDirty()) {
                    $updated++;
                }

                $event->save();
                $keptIds[] = $event->getKey();
            }

            // Libur lama milik seeder yang tidak ada lagi di daftar (termasuk judul ganda).
            $stale = self::seederHolidays()->whereNotIn('id', $keptIds);
            $deletedTitles = $stale->clone()->orderBy('starts_at')->pluck('title')->all();
            $stale->delete();

            return ['created' => $created, 'updated' => $updated, 'deleted' => count($deletedTitles), 'deleted_titles' => $deletedTitles];
        });

        if ($summary['created'] + $summary['updated'] + $summary['deleted'] > 0) {
            $logger->log('holiday.synced', null, $summary);
        }

        $this->command?->info("Hari libur: {$summary['created']} baru, {$summary['updated']} diperbarui, "
            ."{$summary['deleted']} dihapus (".count(self::HOLIDAYS).' total di data).');

        foreach ($summary['deleted_titles'] as $title) {
            $this->command?->line("  - dihapus: {$title}");
        }

        $this->command?->comment('Catatan: Idulfitri & Iduladha bisa bergeser mengikuti sidang isbat Kementerian Agama.');
    }

    /**
     * Satu-satunya cakupan yang boleh diubah/dihapus seeder ini:
     * kategori `holiday` DAN dibuat oleh seeder (bukan oleh pengurus lewat dashboard).
     *
     * @return Builder<Event>
     */
    private static function seederHolidays(): Builder
    {
        return Event::query()
            ->where('category', EventCategory::Holiday->value)
            ->whereNull('created_by');
    }
}
