<?php

namespace App\Filament\Support;

use App\Services\ImageService;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * FileUpload gambar standar (AGENTS.md B4): validasi mimes + mimetypes, maks 2 MB,
 * lalu dikompres menjadi WebP bernama UUID di disk public.
 */
class WebpImageUpload
{
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MIMES = 'mimes:jpg,jpeg,png,webp';

    public static function make(string $name, string $directory, int $maxSize = 1600): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->maxSize(2048)
            ->acceptedFileTypes(self::MIME_TYPES)
            ->rules([self::MIMES])
            ->saveUploadedFileUsing(
                fn (TemporaryUploadedFile $file): string => app(ImageService::class)
                    ->storeWebp($file, $directory, $maxSize)
            )
            ->helperText('JPG/PNG/WebP, maksimal 2 MB. Otomatis dikompres ke WebP.');
    }
}
