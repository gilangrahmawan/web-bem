<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventVisibility;
use App\Policies\EventPolicy;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agenda kalender (AGENTS.md C4). ends_at inklusif; null berarti sama dengan starts_at.
 * Simpan/ubah/hapus lewat App\Services\EventService (otorisasi, log, deteksi bentrok).
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'description',
        'category',
        'visibility',
        'division_id',
        'starts_at',
        'ends_at',
        'all_day',
        'location',
        'organizer',
        'registration_url',
        'has_conflict',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => EventCategory::class,
            'visibility' => EventVisibility::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'has_conflict' => 'boolean',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(EventAssignee::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_assignees')->withTimestamps();
    }

    /** Akhir agenda yang efektif (inklusif). */
    public function effectiveEnd(): CarbonInterface
    {
        return $this->ends_at ?? $this->starts_at;
    }

    /** Deadline tugas konten = tanggal agenda. */
    public function deadline(): CarbonInterface
    {
        return $this->starts_at;
    }

    /** @param  Builder<Event>  $query */
    public function scopePublic(Builder $query): void
    {
        $query->where('visibility', EventVisibility::Public->value);
    }

    /**
     * Agenda yang beririsan dengan rentang [$start, $end] (inklusif).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('starts_at', '<=', $end)
            ->where(fn (Builder $q) => $q
                ->where('ends_at', '>=', $start)
                ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', $start)));
    }

    /** @param  Builder<Event>  $query */
    public function scopeUpcoming(Builder $query): void
    {
        $this->scopeBetween($query, now()->startOfDay(), now()->addYears(5));
    }

    /**
     * Agenda yang boleh dilihat user di kalender dashboard (keputusan pemilik, Fase 3):
     * - Super Admin, Pembina, pemegang event.manage_all: semua agenda.
     * - Semua pengurus: agenda publik, agenda buatannya, rapat/internal umum (tanpa divisi)
     *   dan divisinya; pengelola lintas divisi (sekretaris) melihat semua rapat/internal.
     * - Tugas konten: divisi Medpub & pemegang event.assign_task melihat semua; lainnya hanya yang ditugaskan.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Pengaju kegiatan (bukan pengurus BEM) hanya melihat kalender publik.
        if ($user->isApplicant()) {
            $query->where('visibility', EventVisibility::Public->value);

            return;
        }

        if (EventPolicy::seesAllEvents($user)) {
            return;
        }

        $crossDivision = EventPolicy::managesAllDivisions($user);
        $seesAllContent = EventPolicy::seesAllContentTasks($user);

        $query->where(function (Builder $q) use ($user, $crossDivision, $seesAllContent): void {
            $q->where('visibility', EventVisibility::Public->value)
                ->orWhere('created_by', $user->getKey())
                ->orWhere(function (Builder $q) use ($user, $crossDivision): void {
                    $q->whereIn('category', [EventCategory::Meeting->value, EventCategory::Internal->value]);

                    if (! $crossDivision) {
                        $q->where(fn (Builder $q) => $q
                            ->whereNull('division_id')
                            ->when($user->division_id, fn (Builder $q, int $divisionId) => $q->orWhere('division_id', $divisionId)));
                    }
                })
                ->orWhere(function (Builder $q) use ($user, $seesAllContent): void {
                    $q->where('category', EventCategory::Content->value);

                    if (! $seesAllContent) {
                        $q->whereHas('assignees', fn (Builder $q) => $q->where('user_id', $user->getKey()));
                    }
                });
        });
    }
}
