<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Policies\StudentActivityPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\StudentActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kegiatan (keputusan Fase 7): proposal & LPJ adalah tahap di dalam satu kegiatan.
 * Perpindahan status hanya lewat App\Services\ActivityWorkflowService.
 */
class StudentActivity extends Model
{
    /** @use HasFactory<StudentActivityFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const DIRECTORY = 'activities';

    public const DEFAULT_LPJ_DAYS = 14;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'division_id',
        'name',
        'starts_on',
        'ends_on',
        'location',
        'description',
        'budget_estimate',
        'proposal_path',
        'proposal_name',
        'budget_realization',
        'lpj_path',
        'lpj_name',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'budget_estimate' => 'integer',
            'budget_realization' => 'integer',
            'review_round' => 'integer',
            'proposal_submitted_at' => 'datetime',
            'proposal_approved_at' => 'datetime',
            'lpj_submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'lpj_reminded_h3_at' => 'datetime',
            'lpj_reminded_due_at' => 'datetime',
            'lpj_last_overdue_reminder_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StudentActivityLog::class)->orderBy('id');
    }

    public function picTransfers(): HasMany
    {
        return $this->hasMany(ActivityPicTransfer::class)->latest('id');
    }

    public static function lpjDeadlineDays(): int
    {
        return max(1, (int) (Setting::get('lpj_deadline_days') ?? self::DEFAULT_LPJ_DAYS));
    }

    /** Tenggat LPJ = tanggal selesai acara + lpj_deadline_days. */
    public function lpjDueOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->ends_on)->addDays(self::lpjDeadlineDays());
    }

    /** LPJ terlambat: belum diunggah/diperbaiki dan tenggat sudah lewat. */
    public function isLpjOverdue(): bool
    {
        return $this->status->awaitsLpj() && CarbonImmutable::today()->greaterThan($this->lpjDueOn());
    }

    /** @param  Builder<StudentActivity>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (StudentActivityPolicy::seesAll($user)) {
            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->where('pic_user_id', $user->getKey())->orWhere('created_by', $user->getKey());

            if ($user->isApplicant() && $user->organization_id) {
                $q->orWhere('organization_id', $user->organization_id);
            }

            if (StudentActivityPolicy::isDivisionCoordinator($user)) {
                $q->orWhere(fn (Builder $q) => $q->where('division_id', $user->division_id)
                    ->whereHas('organization', fn (Builder $o) => $o->where('type', 'bem')));
            }
        });
    }

    /**
     * @param  Builder<StudentActivity>  $query
     */
    public function scopeLpjOverdue(Builder $query): void
    {
        $query->whereIn('status', [ActivityStatus::Approved->value, ActivityStatus::LpjRevision->value])
            ->where('ends_on', '<', CarbonImmutable::today()->subDays(self::lpjDeadlineDays())->toDateString());
    }
}
