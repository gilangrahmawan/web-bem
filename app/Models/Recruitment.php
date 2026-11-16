<?php

namespace App\Models;

use Database\Factories\RecruitmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gelombang oprec. Form publik hanya aktif selama opens_at ≤ sekarang ≤ closes_at. */
class Recruitment extends Model
{
    /** @use HasFactory<RecruitmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'opens_at',
        'closes_at',
        'open_divisions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'open_divisions' => 'array',
            'is_active' => 'boolean',
            'results_published_at' => 'datetime',
        ];
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(RecruitmentApplicant::class);
    }

    public function isOpen(): bool
    {
        return $this->is_active && now()->between($this->opens_at, $this->closes_at);
    }

    public function resultsPublished(): bool
    {
        return $this->results_published_at !== null;
    }

    /** @return Collection<int, Division> */
    public function openDivisions(): Collection
    {
        return Division::query()->whereIn('id', array_map('intval', $this->open_divisions ?? []))->orderBy('name')->get();
    }

    /** Gelombang yang sedang dibuka (paling banyak satu). */
    public static function currentlyOpen(): ?self
    {
        return static::query()->where('is_active', true)
            ->where('opens_at', '<=', now())->where('closes_at', '>=', now())
            ->orderBy('opens_at')->first();
    }

    /** @param  Builder<Recruitment>  $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('is_active', true)->where('opens_at', '>', now())->orderBy('opens_at');
    }
}
