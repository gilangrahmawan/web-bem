<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat kegiatan. stage = proposal | lpj | pic | system. */
class StudentActivityLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'student_activity_id',
        'stage',
        'from_status',
        'to_status',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ActivityStatus::class,
            'to_status' => ActivityStatus::class,
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(StudentActivity::class, 'student_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            'proposal' => 'Proposal',
            'lpj' => 'LPJ',
            'pic' => 'Penanggung jawab',
            default => 'Sistem',
        };
    }
}
