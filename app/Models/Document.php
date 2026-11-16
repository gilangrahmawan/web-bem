<?php

namespace App\Models;

use App\Enums\DriveItemType;
use App\Models\Contracts\DriveItem;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen editor Drive. Isi HTML disanitasi saat disimpan (profil purifier 'document') dan saat ditampilkan.
 * Kunci edit 15 menit (locked_by/locked_at) — user lain tidak bisa menyimpan selama kunci berlaku.
 */
class Document extends Model implements DriveItem
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    public const LOCK_MINUTES = 15;

    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'title',
        'has_letterhead',
    ];

    protected function casts(): array
    {
        return [
            'has_letterhead' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Document $document): void {
            if ($document->isDirty('content')) {
                $document->content = self::sanitize($document->content);
            }
        });
    }

    public static function sanitize(?string $html): string
    {
        return (string) clean((string) $html, 'document');
    }

    public function safeContent(): string
    {
        return self::sanitize($this->content);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class)->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLockedFor(User $user): bool
    {
        return $this->locked_by !== null
            && $this->locked_by !== $user->getKey()
            && $this->locked_at !== null
            && $this->locked_at->gt(now()->subMinutes(self::LOCK_MINUTES));
    }

    public function driveType(): DriveItemType
    {
        return DriveItemType::Document;
    }

    public function driveName(): string
    {
        return $this->title;
    }

    public function driveFolderId(): ?int
    {
        return $this->folder_id;
    }

    public function driveOwnerId(): ?int
    {
        return $this->created_by;
    }
}
