<?php

namespace App\Models;

use App\Services\ActivityLogger;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GalleryPhoto extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'gallery_id',
        'path',
        'thumb_path',
        'caption',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // Berkas ikut dihapus saat foto dihapus.
        static::deleted(function (GalleryPhoto $photo): void {
            app(ImageService::class)->delete([$photo->path, $photo->thumb_path]);

            if (Auth::check()) {
                app(ActivityLogger::class)->log('gallery_photo.deleted', $photo, ['gallery_id' => $photo->gallery_id]);
            }
        });
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path);
    }
}
