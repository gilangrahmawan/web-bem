<?php

namespace App\Models;

use App\Enums\FormPurpose;
use App\Enums\FormStatus;
use App\Enums\ScoreVisibility;
use App\Policies\FormPolicy;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Form (Fase 11A). owner null = form mandiri BEM (division_id: milik divisi, dikelola koordinatornya).
 * Diisi publik lewat /form/{public_id} (acak, tidak bisa ditebak).
 */
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory, SoftDeletes;

    public const DEFAULT_RETENTION_MONTHS = 12;

    /** Pengaturan bawaan (docs/SPEC-FASE-11.md A1/A4). */
    public const DEFAULT_SETTINGS = [
        'single_submission' => true,
        'require_login' => false,
        'confirmation_message' => 'Terima kasih, jawaban Anda sudah kami terima.',
        'shuffle_questions' => false,
        'shuffle_options' => false,
        'time_limit_minutes' => null,
        'pass_score' => null,
        'score_visibility' => 'never',
        'retention_months' => self::DEFAULT_RETENTION_MONTHS, // null = simpan selamanya
    ];

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'division_id',
        'is_quiz',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => FormPurpose::class,
            'status' => FormStatus::class,
            'is_quiz' => 'boolean',
            'settings' => 'array',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('position');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function setting(string $key): mixed
    {
        $settings = [...self::DEFAULT_SETTINGS, ...($this->settings ?? [])];

        return $settings[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function allSettings(): array
    {
        return [...self::DEFAULT_SETTINGS, ...($this->settings ?? [])];
    }

    public function scoreVisibility(): ScoreVisibility
    {
        return ScoreVisibility::tryFrom((string) $this->setting('score_visibility')) ?? ScoreVisibility::Never;
    }

    public function timeLimitMinutes(): ?int
    {
        $minutes = (int) $this->setting('time_limit_minutes');

        return $this->is_quiz && $minutes > 0 ? $minutes : null;
    }

    /** Terbit dan dalam jadwal buka/tutup. */
    public function isAcceptingResponses(): bool
    {
        return $this->status === FormStatus::Published
            && ($this->opens_at === null || now()->gte($this->opens_at))
            && ($this->closes_at === null || now()->lte($this->closes_at));
    }

    public function publicUrl(): string
    {
        return route('public.forms.show', $this->public_id);
    }

    public function hasSubmittedResponses(): bool
    {
        return $this->responses()->whereNotNull('submitted_at')->exists();
    }

    /** @param  Builder<Form>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (FormPolicy::seesAll($user)) {
            return;
        }

        if (FormPolicy::isDivisionManager($user)) {
            $query->where('division_id', $user->division_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
