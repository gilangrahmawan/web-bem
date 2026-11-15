<?php

namespace App\Support\Kas;

use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Export grid kas mingguan ke XLSX/CSV (OpenSpout, sudah terpasang lewat Filament — tanpa queue).
 * Nilai teks yang diawali = + - @ (atau tab/CR) diberi prefiks ' agar tidak dieksekusi
 * sebagai rumus oleh Excel/Sheets (CSV/formula injection).
 */
class KasExport
{
    /**
     * @param  Collection<int, KasMemberSummary>  $summaries
     * @param  list<KasWeek>  $weeks
     * @return list<list<string|int>>
     */
    public static function rows(Collection $summaries, array $weeks, int $currentWeek): array
    {
        $header = ['Nama', 'Divisi', 'Jabatan', 'Mulai wajib kas'];

        foreach ($weeks as $week) {
            $header[] = "M{$week->number} ({$week->start->format('d/m/Y')})";
        }

        array_push($header, 'Total dibayar (Rp)', 'Minggu jatuh tempo', 'Minggu menunggak', 'Tunggakan (Rp)', 'Status');

        $rows = [$header];

        foreach ($summaries as $summary) {
            $row = [
                $summary->user->name,
                $summary->user->division?->name ?? '-',
                $summary->user->position ?? '-',
                $summary->firstWeek ? 'Minggu ke-'.$summary->firstWeek : '-',
            ];

            foreach ($weeks as $week) {
                $row[] = match (true) {
                    $summary->isPaid($week->number) => $summary->payments[$week->number],
                    ! $summary->isRequired($week->number) => '-',
                    $week->number <= $currentWeek => 'BELUM',
                    default => '',
                };
            }

            array_push(
                $row,
                $summary->paidTotal(),
                count($summary->dueWeeks),
                count($summary->unpaidWeeks),
                $summary->arrears,
                $summary->isClear() ? 'Lunas' : 'Menunggak',
            );

            $rows[] = $row;
        }

        return array_map(fn (array $row) => array_map(self::sanitize(...), $row), $rows);
    }

    /** @param  list<list<string|int>>  $rows */
    public static function write(array $rows, string $format): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kas-').'.'.$format;
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;

        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    public static function fileName(string $format): string
    {
        return 'kas-mingguan-'.now()->format('Y-m-d').'.'.$format;
    }

    public static function sanitize(string|int|null $value): string|int
    {
        if (is_int($value)) {
            return $value;
        }

        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && $value !== '-'
            ? "'".$value
            : $value;
    }
}
