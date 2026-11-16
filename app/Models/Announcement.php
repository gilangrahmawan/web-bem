<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Pengumuman / surat. Dibuat & dikirim lewat AnnouncementService::send(); tidak bisa diedit setelah terkirim.
 */
class Announcement extends Model
{
    public const DISK = 'local';

    public const DIRECTORY = 'announcements';

    /** @var list<string> */
    protected $fillable = [
        'type',
        'title',
        'body',
        'attachment_path',
        'attachment_name',
        'target_all',
        'target_all_organizations',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'target_all' => 'boolean',
            'target_all_organizations' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Announcement $announcement): void {
            if ($announcement->attachment_path) {
                Storage::disk(self::DISK)->delete($announcement->attachment_path);
            }
        });
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<Announcement>  $query */
    public function scopeOfType(Builder $query, AnnouncementType $type): void
    {
        $query->where('type', $type->value);
    }
}
