<?php

namespace App\Models;

use App\Enums\DriveFileKind;
use App\Enums\DriveItemType;
use App\Models\Contracts\DriveItem;
use Database\Factories\DriveFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Berkas unggahan Drive (tabel `files`). Disimpan ASLI di disk privat dengan nama UUID;
 * hanya thumbnail yang WebP. Diunduh lewat DriveFileController (berotorisasi, 404 bila tidak berhak).
 */
class DriveFile extends Model implements DriveItem
{
    /** @use HasFactory<DriveFileFactory> */
    use HasFactory, SoftDeletes;

    public const DIRECTORY = 'drive/files';

    public const THUMB_DIRECTORY = 'drive/thumbs';

    protected $table = 'files';

    /** @var list<string> */
    protected $fillable = [
        'folder_id',
        'original_name',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DriveFileKind::class,
            'size' => 'integer',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function path(): string
    {
        return self::DIRECTORY.'/'.$this->stored_name;
    }

    public function driveType(): DriveItemType
    {
        return DriveItemType::File;
    }

    public function driveName(): string
    {
        return $this->original_name;
    }

    public function driveFolderId(): ?int
    {
        return $this->folder_id;
    }

    public function driveOwnerId(): ?int
    {
        return $this->uploaded_by;
    }
}
