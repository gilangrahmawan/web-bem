<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penilaian interview (1–5) + catatan INTERNAL dari satu pewawancara. */
class RecruitmentInterview extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'recruitment_applicant_id',
        'interviewer_id',
        'score',
        'note',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplicant::class, 'recruitment_applicant_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}
