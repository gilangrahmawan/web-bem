<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Penulis XLSX/CSV bersama (OpenSpout, tanpa queue) dengan perlindungan CSV/formula injection:
 * teks yang diawali = + - @ (atau tab/CR) diberi prefiks ' agar tidak dieksekusi Excel/Sheets.
 */
class SpreadsheetExport
{
    /** @param  list<list<string|int|float|null>>  $rows */
    public static function write(array $rows, string $format): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export-').'.'.$format;
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;

        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(self::sanitize(...), $row)));
        }

        $writer->close();

        return $path;
    }

    public static function sanitize(string|int|float|null $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = (string) $value;

        return $value !== '' && $value !== '-' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
