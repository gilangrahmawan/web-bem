<?php

namespace App\Models;

use App\Enums\DownloadCategory;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas unduhan publik. Berkas disimpan di disk privat (`local`) dengan nama UUID
 * dan hanya bisa diunduh lewat DownloadController (berkas nonaktif → 404).
 */
class Download extends Model
{
    use HasFactory;

    public const DISK = 'local';

    public const DIRECTORY = 'downloads';

    /** @var list<string> */
    protected $fillable = [
        'title',
        'category',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => DownloadCategory::class,
            'is_active' => 'boolean',
            'size' => 'integer',
            'download_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Berkas lama dihapus saat diganti.
        static::updated(function (Download $download): void {
            if ($download->wasChanged('file_path') && $old = $download->getOriginal('file_path')) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        // Hapus berkas fisik + catat (AGENTS.md B4: log hapus berkas).
        static::deleted(function (Download $download): void {
            Storage::disk(self::DISK)->delete($download->file_path);

            if (Auth::check()) {
                app(ActivityLogger::class)->log('download.deleted', $download, [
                    'title' => $download->title,
                    'file' => $download->original_name,
                ]);
            }
        });
    }

    /** @param  Builder<Download>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'FILE');
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return match (true) {
            $size >= 1_048_576 => number_format($size / 1_048_576, 1, ',', '.').' MB',
            $size >= 1024 => number_format($size / 1024, 0, ',', '.').' KB',
            default => $size.' B',
        };
    }
}
