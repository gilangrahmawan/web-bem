<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Periode kepengurusan (kabinet). Hanya satu yang boleh aktif;
 * pengaktifan dilakukan lewat PeriodService.
 */
class Period extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<Period>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::query()->active()->first();
    }
}
