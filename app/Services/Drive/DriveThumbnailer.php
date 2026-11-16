<?php

namespace App\Services\Drive;

use App\Enums\DriveFileKind;
use App\Models\DriveFile;
use App\Services\ImageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Thumbnail kartu Drive (WebP, disk privat). Berkas ASLI tidak pernah diubah.
 * - Gambar: GD lewat ImageService (decode sekali, resize sebelum encode).
 * - PDF: halaman pertama lewat Imagick + Ghostscript HANYA bila tersedia di server; selain itu null
 *   → kartu memakai ikon jenis berkas (shared hosting umumnya tanpa Imagick).
 * - Jenis lain: selalu ikon.
 */
class DriveThumbnailer
{
    public const SIZE = 480;

    public function __construct(private readonly ImageService $images) {}

    public function make(DriveFile $file, string $absolutePath): ?string
    {
        try {
            return match ($file->kind) {
                DriveFileKind::Image => $this->images->storeWebp($absolutePath, DriveFile::THUMB_DIRECTORY, self::SIZE, $file->disk),
                DriveFileKind::Pdf => self::pdfSupported() ? $this->pdf($file, $absolutePath) : null,
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('Thumbnail Drive gagal dibuat; memakai ikon.', ['file_id' => $file->getKey(), 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Imagick terpasang DAN bisa membaca PDF (butuh Ghostscript). */
    public static function pdfSupported(): bool
    {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        if (! extension_loaded('imagick') || ! class_exists(\Imagick::class)) {
            return $supported = false;
        }

        try {
            return $supported = in_array('PDF', \Imagick::queryFormats('PDF'), true);
        } catch (Throwable) {
            return $supported = false;
        }
    }

    private function pdf(DriveFile $file, string $absolutePath): ?string
    {
        $imagick = new \Imagick;
        $imagick->setResolution(72, 72);
        $imagick->readImage($absolutePath.'[0]');
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imagick->thumbnailImage(self::SIZE, self::SIZE, true);
        $imagick->setImageFormat('webp');

        $path = DriveFile::THUMB_DIRECTORY.'/'.pathinfo($file->stored_name, PATHINFO_FILENAME).'.webp';
        Storage::disk($file->disk)->put($path, $imagick->getImageBlob());
        $imagick->clear();

        return $path;
    }
}
