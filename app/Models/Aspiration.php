<?php

namespace App\Models;

use App\Enums\AspirationCategory;
use App\Enums\AspirationStatus;
use App\Policies\AspirationPolicy;
use Database\Factories\AspirationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aspirasi mahasiswa. Perpindahan status hanya lewat App\Services\AspirationService.
 * Nama & kontak pengirim hanya boleh ditampilkan ke Humas & Super Admin (AspirationPolicy::seesIdentity).
 */
class Aspiration extends Model
{
    /** @use HasFactory<AspirationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'tracking_code',
        'name',
        'contact',
        'is_anonymous',
        'category',
        'body',
        'status',
        'response',
        'rejection_reason',
        'handled_by',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'category' => AspirationCategory::class,
            'status' => AspirationStatus::class,
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AspirationLog::class)->orderBy('id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    /** Pernah diteruskan ke Kajian. */
    public function reachedKajian(): bool
    {
        return $this->logs()->where('to_status', AspirationStatus::InStudy->value)->exists();
    }

    /** @param  Builder<Aspiration>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (AspirationPolicy::seesAll($user)) {
            return;
        }

        if (AspirationPolicy::seesKajianStage($user)) {
            $query->whereHas('logs', fn (Builder $q) => $q->where('to_status', AspirationStatus::InStudy->value));

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
