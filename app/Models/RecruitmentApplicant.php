<?php

namespace App\Models;

use App\Enums\ApplicantStatus;
use App\Policies\RecruitmentApplicantPolicy;
use Database\Factories\RecruitmentApplicantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pendaftar oprec. CV & foto di disk privat (diunduh lewat RecruitmentFileController).
 * Catatan & nilai interview INTERNAL — tidak pernah tampil di halaman cek hasil.
 */
class RecruitmentApplicant extends Model
{
    /** @use HasFactory<RecruitmentApplicantFactory> */
    use HasFactory;

    public const DISK = 'local';

    /** @var list<string> */
    protected $fillable = [
        'recruitment_id',
        'registration_code',
        'name',
        'nim',
        'prodi',
        'batch_year',
        'phone',
        'email',
        'first_choice_division_id',
        'second_choice_division_id',
        'motivation',
        'experience',
        'cv_path',
        'cv_name',
        'photo_path',
        'consent_at',
    ];

    protected $attributes = [
        'status' => 'registered',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
            'batch_year' => 'integer',
            'interview_at' => 'datetime',
            'consent_at' => 'datetime',
            'data_purged_at' => 'datetime',
        ];
    }

    public function recruitment(): BelongsTo
    {
        return $this->belongsTo(Recruitment::class);
    }

    public function firstChoice(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'first_choice_division_id');
    }

    public function secondChoice(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'second_choice_division_id');
    }

    public function acceptedDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'accepted_division_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(RecruitmentInterview::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecruitmentApplicantLog::class)->orderBy('id');
    }

    public function averageScore(): ?float
    {
        $avg = $this->interviews()->avg('score');

        return $avg === null ? null : round((float) $avg, 2);
    }

    /**
     * Status yang boleh dilihat pendaftar. Sebelum "Umumkan hasil", keputusan akhir (termasuk ditolak
     * di tahap berkas) disamarkan sebagai tahap terakhir sebelum keputusan agar tidak bocor lebih awal.
     */
    public function publicStage(): ApplicantStatus
    {
        if (! $this->status->isFinal() || $this->recruitment?->resultsPublished()) {
            return $this->status;
        }

        $before = $this->logs()->where('to_status', $this->status->value)->latest('id')->value('from_status');

        return $before !== null
            ? ApplicantStatus::from($before instanceof ApplicantStatus ? $before->value : $before)
            : ($this->interview_at ? ApplicantStatus::Interview : ApplicantStatus::Registered);
    }

    /** @return list<int> */
    public function choiceDivisionIds(): array
    {
        return array_values(array_filter([$this->first_choice_division_id, $this->second_choice_division_id]));
    }

    /** @param  Builder<RecruitmentApplicant>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (RecruitmentApplicantPolicy::seesAll($user)) {
            return;
        }

        if (RecruitmentApplicantPolicy::isInterviewer($user) && $user->division_id) {
            $query->where(fn (Builder $q) => $q
                ->where('first_choice_division_id', $user->division_id)
                ->orWhere('second_choice_division_id', $user->division_id));

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
