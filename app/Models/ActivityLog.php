<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Log aktivitas (read-only dari dashboard). Tulis lewat App\Services\ActivityLogger.
 */
class ActivityLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Label Indonesia untuk kode aksi. */
    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'auth.login' => 'Login',
            'auth.failed' => 'Gagal login',
            'auth.lockout' => 'Login dikunci',
            'auth.logout' => 'Logout',
            'auth.password_changed' => 'Ganti password',
            'user.created' => 'User dibuat',
            'user.updated' => 'User diubah',
            'user.roles_changed' => 'Role user diubah',
            'user.password_reset' => 'Password direset',
            'user.activated' => 'User diaktifkan',
            'user.deactivated' => 'User dinonaktifkan',
            'role.created' => 'Role dibuat',
            'role.updated' => 'Role diubah',
            'role.deleted' => 'Role dihapus',
            'role.permissions_added' => 'Permission role ditambah',
            'role.permissions_removed' => 'Permission role dicabut',
            'period.activated' => 'Periode diaktifkan',
            'setting.updated' => 'Pengaturan diubah',
            default => $action,
        };
    }
}
