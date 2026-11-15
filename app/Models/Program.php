<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Program kerja divisi per periode. */
class Program extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'division_id',
        'period_id',
        'title',
        'description',
        'status',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
