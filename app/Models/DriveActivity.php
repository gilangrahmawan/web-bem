<?php

namespace App\Models;

use App\Enums\DriveItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak aktivitas Drive (untuk "Terbaru" dan widget aktivitas). Nama item disimpan agar tetap terbaca setelah dihapus. */
class DriveActivity extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'item_type',
        'item_id',
        'item_name',
        'folder_id',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => DriveItemType::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'upload' => 'mengunggah',
            'create' => 'membuat',
            'rename' => 'mengganti nama',
            'move' => 'memindahkan',
            'delete' => 'membuang',
            'restore' => 'memulihkan',
            'force_delete' => 'menghapus permanen',
            'share' => 'membagikan',
            'open' => 'membuka',
            'edit' => 'mengedit',
            'archive' => 'mengarsipkan',
            default => $this->action,
        };
    }
}
