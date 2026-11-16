<?php

namespace App\Models;

use App\Enums\DriveItemType;
use App\Models\Contracts\DriveItem;
use App\Services\Drive\DriveAccessResolver;
use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Folder Drive (bertingkat). Folder sistem (mis. "Surat") tidak bisa diganti nama, dipindah, atau dihapus. */
class Folder extends Model implements DriveItem
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => app(DriveAccessResolver::class)->flush();

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
        static::forceDeleted($flush);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DriveFile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(FolderPermission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function driveType(): DriveItemType
    {
        return DriveItemType::Folder;
    }

    public function driveName(): string
    {
        return $this->name;
    }

    public function driveFolderId(): ?int
    {
        return $this->parent_id;
    }

    public function driveOwnerId(): ?int
    {
        return $this->created_by;
    }
}
