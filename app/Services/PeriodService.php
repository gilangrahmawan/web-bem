<?php

namespace App\Services;

use App\Models\Period;
use Illuminate\Support\Facades\DB;

class PeriodService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** Mengaktifkan satu periode dan menonaktifkan semua periode lain. */
    public function activate(Period $period): void
    {
        DB::transaction(function () use ($period): void {
            Period::query()->whereKeyNot($period->getKey())->where('is_active', true)
                ->update(['is_active' => false]);

            $period->forceFill(['is_active' => true])->save();

            $this->logger->log('period.activated', $period, ['name' => $period->name]);
        });
    }
}
