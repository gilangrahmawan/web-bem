<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\RoleName;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Organisasi pengaju kegiatan (UKM, HMJ, BEM, Panitia, Lainnya). */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    public const BEM_NAME = 'BEM UBHINUS';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Akun pengaju kegiatan aktif di organisasi ini. */
    public function applicants(): HasMany
    {
        return $this->users()->where('is_active', true)->role(RoleName::PengajuKegiatan->value);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StudentActivity::class);
    }

    public function isBem(): bool
    {
        return $this->type === OrganizationType::Bem;
    }

    public static function bem(): ?self
    {
        return static::query()->where('type', OrganizationType::Bem->value)->orderBy('id')->first();
    }

    /** @param  Builder<Organization>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
