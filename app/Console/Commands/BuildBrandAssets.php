<?php

namespace App\Console\Commands;

use App\Services\ImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;

/**
 * Membuat aset logo web (public/images) dari berkas asli di folder _aset.
 * Berkas asli tidak diubah. Jalankan ulang bila logo diganti.
 */
#[Signature('brand:assets')]
#[Description('Membuat logo WebP, favicon, dan gambar Open Graph dari folder _aset')]
class BuildBrandAssets extends Command
{
    public function handle(ImageService $images): int
    {
        $source = base_path('_aset');
        $target = public_path('images');

        $bem = $source.DIRECTORY_SEPARATOR.'logo bem.png';
        $kampus = $source.DIRECTORY_SEPARATOR.'ubhinus.png';

        foreach ([$bem, $kampus] as $file) {
            if (! is_file($file)) {
                $this->error("Berkas tidak ditemukan: {$file}");

                return self::FAILURE;
            }
        }

        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $manager = $images->manager();

        $manager->decodePath($bem)->scaleDown(512, 512)
            ->encode(new WebpEncoder(85))->save($target.'/logo-bem.webp');
        $manager->decodePath($bem)->scaleDown(160, 160)
            ->encode(new PngEncoder)->save($target.'/logo-bem-160.png');
        $manager->decodePath($bem)->contain(180, 180, 'ffffff00')
            ->encode(new PngEncoder)->save($target.'/apple-touch-icon.png');
        $manager->decodePath($bem)->contain(48, 48, 'ffffff00')
            ->encode(new PngEncoder)->save(public_path('favicon.png'));
        $manager->decodePath($kampus)->scaleDown(256, 256)
            ->encode(new WebpEncoder(85))->save($target.'/logo-ubhinus.webp');

        // Gambar Open Graph default 1200x630: latar navy + logo di tengah.
        $logo = $manager->decodePath($bem)->scaleDown(420, 420);
        $manager->createImage(1200, 630)->fill('232361')
            ->insert($logo, 0, 0, 'center')
            ->encode(new PngEncoder)->save($target.'/og-default.png');

        // Ikon PWA (manifest): biasa (latar transparan) + maskable (latar navy, logo di zona aman ±60%).
        foreach ([192, 512] as $size) {
            $manager->decodePath($bem)->contain($size, $size, 'ffffff00')
                ->encode(new PngEncoder)->save($target."/icon-{$size}.png");

            $inner = $manager->decodePath($bem)->scaleDown((int) round($size * 0.6), (int) round($size * 0.6));
            $manager->createImage($size, $size)->fill('232361')
                ->insert($inner, 0, 0, 'center')
                ->encode(new PngEncoder)->save($target."/icon-maskable-{$size}.png");
        }

        // favicon.ico (16/32/48 px, isi PNG) — browser selalu meminta /favicon.ico.
        $sizes = [16, 32, 48];
        $pngs = array_map(
            fn (int $size): string => (string) $manager->decodePath($bem)->contain($size, $size, 'ffffff00')->encode(new PngEncoder),
            $sizes,
        );
        file_put_contents(public_path('favicon.ico'), self::icoFromPngs($sizes, $pngs));

        $this->info('Aset logo dibuat di public/images, public/favicon.png, public/favicon.ico (termasuk ikon PWA).');

        return self::SUCCESS;
    }

    /**
     * Wadah ICO berisi gambar PNG (didukung semua browser modern & Windows Vista+).
     *
     * @param  list<int>  $sizes
     * @param  list<string>  $pngs
     */
    public static function icoFromPngs(array $sizes, array $pngs): string
    {
        $count = count($pngs);
        $header = pack('vvv', 0, 1, $count);
        $directory = '';
        $offset = 6 + 16 * $count;

        foreach ($pngs as $i => $png) {
            $size = $sizes[$i] >= 256 ? 0 : $sizes[$i];
            $directory .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($png), $offset);
            $offset += strlen($png);
        }

        return $header.$directory.implode('', $pngs);
    }
}
