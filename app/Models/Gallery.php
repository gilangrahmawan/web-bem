<?php

namespace App\Models;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Gallery extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'slug',
        'event_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Hapus foto satu per satu agar berkasnya ikut terhapus (cascade DB tidak memicu event model).
        static::deleting(function (Gallery $gallery): void {
            $gallery->photos()->get()->each->delete();
        });

        static::deleted(function (Gallery $gallery): void {
            if (Auth::check()) {
                app(ActivityLogger::class)->log('gallery.deleted', $gallery, ['title' => $gallery->title]);
            }
        });
    }

    public function photos(): HasMany
    {
        return $this->hasMany(GalleryPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Foto pertama sebagai sampul album. */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(GalleryPhoto::class)->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }
}
