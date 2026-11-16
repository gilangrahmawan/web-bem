<?php

namespace App\Filament\Support;

use App\Services\ImageService;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * FileUpload gambar standar (AGENTS.md B4): validasi mimes + mimetypes, lalu dikompres menjadi WebP
 * bernama UUID di disk public.
 *
 * Foto HP (4000px, 5–8 MB) diperkecil DI BROWSER sebelum dikirim (FilePond image transform, sisi
 * terpanjang ≤ CLIENT_MAX_SIDE px), sehingga yang terunggah biasanya < 1 MB. Karena FilePond memeriksa
 * ukuran SEBELUM resize, batas di browser = batas upload PHP (12 MB); batas 2 MB (B4) diperiksa server
 * terhadap berkas yang benar-benar diterima (hasil resize).
 *
 * JANGAN dipakai untuk cover konten Medpub (perlu file asli resolusi penuh — PostCoverUpload) atau Drive.
 */
class WebpImageUpload
{
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MIMES = 'mimes:jpg,jpeg,png,webp';

    /** Sisi terpanjang hasil resize di browser. */
    public const CLIENT_MAX_SIDE = 1600;

    /** Batas berkas yang boleh DIPILIH di browser (KB) — sama dengan upload_max_filesize 12M. */
    public const CLIENT_PICK_MAX_KB = 12288;

    /** Batas berkas yang DITERIMA server setelah resize (KB), AGENTS.md B4. */
    public const SERVER_MAX_KB = 2048;

    public static function make(string $name, string $directory, int $maxSize = 1600): FileUpload
    {
        return self::resizeInBrowser(FileUpload::make($name), $maxSize)
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->saveUploadedFileUsing(
                fn (TemporaryUploadedFile $file): string => app(ImageService::class)
                    ->storeWebp($file, $directory, $maxSize)
            );
    }

    /**
     * Terapkan aturan foto terkompres ke FileUpload apa pun (juga yang menyimpan sendiri, mis. galeri).
     * $maxSize = sisi terpanjang hasil akhir di server; resize browser tidak pernah lebih kecil dari itu.
     */
    public static function resizeInBrowser(FileUpload $upload, int $maxSize = 1600): FileUpload
    {
        $side = (string) max(self::CLIENT_MAX_SIDE, $maxSize);

        return $upload
            ->image()
            ->acceptedFileTypes(self::MIME_TYPES)
            ->maxSize(self::CLIENT_PICK_MAX_KB)
            ->rules([self::MIMES, 'max:'.self::SERVER_MAX_KB])
            ->validationMessages(['max' => 'Foto masih lebih dari 2 MB setelah diperkecil. Coba simpan ulang sebagai JPG.'])
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth($side)
            ->automaticallyResizeImagesToHeight($side)
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->uploadingMessage('Memperkecil & mengunggah foto…')
            ->helperText('JPG/PNG/WebP. Foto besar dari HP otomatis diperkecil sebelum diunggah, lalu dikompres ke WebP.');
    }
}
