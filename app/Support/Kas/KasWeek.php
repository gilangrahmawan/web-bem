<?php

namespace App\Support\Kas;

use App\Support\Format;
use Carbon\CarbonImmutable;

/** Satu minggu kas (Senin–Minggu). Minggu ke-1 = Senin pertama pada/setelah kas_start_date. */
final readonly class KasWeek
{
    public function __construct(
        public int $number,
        public CarbonImmutable $start,
    ) {}

    public function end(): CarbonImmutable
    {
        return $this->start->addDays(6);
    }

    /** Minggu ini sudah dimulai (jatuh tempo) per tanggal $today. */
    public function hasStarted(CarbonImmutable $today): bool
    {
        return $this->start->lessThanOrEqualTo($today);
    }

    /** "2 Nov" — label kolom grid. */
    public function shortLabel(): string
    {
        return $this->start->locale('id')->translatedFormat('j M');
    }

    /** "2–8 November 2026" */
    public function rangeLabel(): string
    {
        return $this->start->isSameMonth($this->end())
            ? $this->start->format('j').'–'.Format::date($this->end())
            : $this->start->locale('id')->translatedFormat('j M').' – '.Format::date($this->end());
    }

    public function monthKey(): string
    {
        return $this->start->format('Y-m');
    }
}
