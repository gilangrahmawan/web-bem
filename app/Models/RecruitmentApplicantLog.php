<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat status pendaftar oprec (catatan internal). */
class RecruitmentApplicantLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'recruitment_applicant_id',
        'from_status',
        'to_status',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ApplicantStatus::class,
            'to_status' => ApplicantStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
