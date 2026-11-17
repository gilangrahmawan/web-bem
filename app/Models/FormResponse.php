<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Jawaban satu responden. Tanpa IP. resume_token disimpan sebagai hash. */
class FormResponse extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'form_id',
    ];

    protected function casts(): array
    {
        return [
            'question_order' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'graded_at' => 'datetime',
            'score' => 'float',
            'max_score' => 'float',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    public function respondent(): MorphTo
    {
        return $this->morphTo();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->gt($this->expires_at);
    }

    public function needsManualGrading(): bool
    {
        return $this->isSubmitted() && $this->graded_at === null;
    }
}
