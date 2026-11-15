<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Kompres gambar upload menjadi WebP (AGENTS.md B2/B5).
 * Nama file selalu UUID; semua akses lewat Storage::disk().
 */
class ImageService
{
    public const QUALITY = 80;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Mengubah gambar menjadi WebP (sisi terpanjang maks $maxSize px) dan menyimpannya.
     *
     * @return string path relatif di disk
     */
    public function storeWebp(
        UploadedFile|string $source,
        string $directory,
        int $maxSize = 1600,
        string $disk = 'public',
        ?string $name = null,
    ): string {
        $image = $this->read($source);
        $image->scaleDown($maxSize, $maxSize);

        $path = trim($directory, '/').'/'.($name ?? Str::uuid()->toString()).'.webp';

        Storage::disk($disk)->put($path, (string) $image->encode(new WebpEncoder(self::QUALITY)));

        return $path;
    }

    /**
     * Menyimpan versi besar + thumbnail (dipotong rapi) dari satu gambar.
     *
     * @return array{path: string, thumb_path: string}
     */
    public function storeWithThumbnail(
        UploadedFile|string $source,
        string $directory,
        int $maxSize = 1600,
        int $thumbWidth = 480,
        int $thumbHeight = 360,
        string $disk = 'public',
    ): array {
        $uuid = Str::uuid()->toString();
        $directory = trim($directory, '/');
        $path = "{$directory}/{$uuid}.webp";
        $thumbPath = "{$directory}/thumbs/{$uuid}.webp";

        // Decode sekali saja (hemat memori di shared hosting): versi besar dulu,
        // lalu thumbnail dibuat dari versi besar yang sudah diperkecil.
        $image = $this->read($source)->scaleDown($maxSize, $maxSize);
        Storage::disk($disk)->put($path, (string) $image->encode(new WebpEncoder(self::QUALITY)));

        $image->coverDown($thumbWidth, $thumbHeight);
        Storage::disk($disk)->put($thumbPath, (string) $image->encode(new WebpEncoder(self::QUALITY)));
        unset($image);

        return ['path' => $path, 'thumb_path' => $thumbPath];
    }

    /** @param  list<string|null>  $paths */
    public function delete(array $paths, string $disk = 'public'): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths !== []) {
            Storage::disk($disk)->delete($paths);
        }
    }

    public function manager(): ImageManager
    {
        return $this->manager;
    }

    private function read(UploadedFile|string $source): ImageInterface
    {
        $image = $source instanceof UploadedFile
            ? $this->manager->decodePath($source->getRealPath())
            : $this->manager->decodePath($source);

        // Putar sesuai EXIF (foto HP) sebelum diproses.
        return $image->orient();
    }
}
