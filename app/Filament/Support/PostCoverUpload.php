<?php

namespace App\Filament\Support;

use App\Services\PostContentService;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Upload cover konten: WebP terkompres untuk website + gambar ASLI resolusi penuh di disk privat
 * untuk diunduh tim medsos (keputusan pemilik Fase 6). Validasi mimes + mimetypes.
 * Batas 10 MB karena file asli resolusi penuh dipertahankan (bukan 2 MB seperti gambar biasa).
 */
class PostCoverUpload
{
    public static function make(string $name = 'cover'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Gambar cover')
            ->image()
            ->disk('public')
            ->directory(PostContentService::COVER_DIRECTORY)
            ->visibility('public')
            ->maxSize(10240)
            ->acceptedFileTypes(WebpImageUpload::MIME_TYPES)
            ->rules([WebpImageUpload::MIMES])
            ->saveUploadedFileUsing(
                fn (TemporaryUploadedFile $file): string => app(PostContentService::class)->storeUploadedCover($file)
            )
            ->helperText('JPG/PNG/WebP maksimal 10 MB. Website memakai versi WebP terkompres; file asli resolusi penuh disimpan untuk diunduh tim medsos.');
    }
}
