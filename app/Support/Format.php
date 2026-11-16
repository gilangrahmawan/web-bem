<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Format tampilan Indonesia (AGENTS.md B3). */
class Format
{
    /** "15 November 2026" */
    public static function date(?CarbonInterface $date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y') : '';
    }

    /** "Rp5.000" */
    public static function rupiah(int|float|null $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }

    /** "512 KB", "1,5 MB", "2 GB" */
    public static function bytes(int|float|null $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        $decimals = $i <= 1 || $bytes >= 100 || floor($bytes) == $bytes ? 0 : 1;

        return number_format($bytes, $decimals, ',', '.').' '.$units[$i];
    }
}
