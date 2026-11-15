<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu minggu kas yang sudah dibayar. Catat/batal lewat App\Services\KasService.
 * `amount` = nominal saat dicatat (perubahan nominal tidak mengubah riwayat).
 */
class KasWeeklyPayment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'period_id',
        'user_id',
        'week_number',
        'week_start',
        'amount',
        'paid_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
            'week_start' => 'date',
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
