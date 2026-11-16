<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Pengurus BEM. User tidak pernah dihapus permanen; gunakan is_active=false.
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'division_id',
        'organization_id',
        'position',
        'kas_starts_on',
        'photo',
        'is_active',
        'must_change_password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'kas_starts_on' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'notification_preferences' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Foto profil lama dihapus saat diganti.
        static::updated(function (User $user): void {
            if ($user->wasChanged('photo') && $old = $user->getOriginal('photo')) {
                Storage::disk('public')->delete($old);
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Akun organisasi pengaju kegiatan — BUKAN pengurus BEM (keputusan Fase 7). */
    public function isApplicant(): bool
    {
        return $this->hasRole(RoleName::PengajuKegiatan->value);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function kasPayments(): HasMany
    {
        return $this->hasMany(KasWeeklyPayment::class);
    }

    public function duePayments(): HasMany
    {
        return $this->hasMany(DuePayment::class);
    }

    /** Tugas konten Medpub yang ditugaskan ke user ini. */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(EventAssignee::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin' || ! $this->is_active || ! $this->roles()->exists()) {
            return false;
        }

        // Pengaju kegiatan wajib terhubung ke organisasi yang aktif.
        return ! $this->isApplicant()
            || ($this->organization_id !== null && (bool) $this->organization?->is_active);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /**
     * Boleh memakai chat (AGENTS.md C6): akun aktif ber-role, BUKAN Pembina.
     * Pengaju kegiatan boleh (personal, grup organisasi, grup buatan sendiri).
     */
    public function canUseChat(): bool
    {
        if (! $this->is_active || ! $this->roles()->exists()) {
            return false;
        }

        return $this->getRoleNames()->reject(fn (string $role) => $role === RoleName::Pembina->value)->isNotEmpty();
    }

    /** Pengurus BEM (bukan pengaju, bukan Pembina) — anggota grup divisi otomatis. */
    public function isChatStaff(): bool
    {
        return $this->canUseChat() && ! $this->isApplicant();
    }

    /** Push untuk jenis ini aktif? (null = semua aktif; System selalu aktif). Lonceng tidak terpengaruh. */
    public function wantsPush(NotificationCategory $category): bool
    {
        if ($category === NotificationCategory::System) {
            return true;
        }

        return (bool) (($this->notification_preferences ?? [])[$category->value] ?? true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    /** @param  Builder<User>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<User>  $query */
    public function scopeActiveSuperAdmins(Builder $query): void
    {
        $query->where('is_active', true)->role(RoleName::SuperAdmin->value);
    }
}
