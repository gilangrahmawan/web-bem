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
}
