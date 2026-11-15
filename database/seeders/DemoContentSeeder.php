<?php

namespace Database\Seeders;

use App\Enums\DownloadCategory;
use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\ProgramStatus;
use App\Models\Division;
use App\Models\Download;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Models\Period;
use App\Models\Post;
use App\Models\Program;
use App\Models\StructureMember;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\PngEncoder;
use RuntimeException;

/**
 * Konten contoh untuk mencoba website publik di lokal.
 * HANYA UNTUK LOKAL — dipanggil dari DemoSeeder (APP_ENV=local).
 */
class DemoContentSeeder extends Seeder
{
    private const COLORS = ['232361', '000078', '1b1b4d', '0e7490', 'b45309', '15803d'];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoContentSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        if (Post::query()->where('slug', 'like', 'contoh-%')->exists()) {
            $this->command?->warn('Konten demo sudah ada; dilewati.');

            return;
        }

        $period = $this->period();
        $divisions = Division::query()->pluck('id', 'slug');
        $author = User::query()->where('email', 'koordinator.medpub@demo.test')->first()
            ?? User::query()->firstOrFail();

        $this->structure($period, $divisions->all());
        $this->programs($period, $divisions->all());
        $this->posts($author);
        $this->galleries();
        $this->downloads();

        $this->command?->info('Konten demo dibuat: struktur, proker, berita, kajian, galeri, unduhan.');
    }

    private function period(): Period
    {
        $period = Period::query()->firstOrCreate(
            ['name' => 'Kabinet Contoh 2026/2027'],
            ['starts_on' => '2026-08-01', 'ends_on' => '2027-07-31'],
        );

        if (! Period::query()->active()->exists()) {
            $period->forceFill(['is_active' => true])->save();
        }

        return Period::current() ?? $period;
    }

    /** @param  array<string, int>  $divisions */
    private function structure(Period $period, array $divisions): void
    {
        $prodi = ['Informatika', 'Sistem Informasi', 'Manajemen', 'Akuntansi', 'Ilmu Hukum', 'Ilmu Komunikasi'];

        $members = [
            ['bph', 'Ketua Umum', 'super.admin@demo.test'],
            ['bph', 'Wakil Ketua Umum', null],
            ['bph', 'Sekretaris', 'sekretaris@demo.test'],
            ['bph', 'Bendahara', 'bendahara@demo.test'],
            ['kajian', 'Koordinator Kajian', 'koordinator.kajian@demo.test'],
            ['kajian', 'Anggota Kajian', 'anggota.kajian@demo.test'],
            ['kajian', 'Anggota Kajian', null],
            ['medpub', 'Koordinator Media & Publikasi', 'koordinator.medpub@demo.test'],
            ['medpub', 'Anggota Media & Publikasi', 'anggota.medpub@demo.test'],
            ['medpub', 'Anggota Media & Publikasi', null],
            ['humas', 'Koordinator Humas', 'koordinator.humas@demo.test'],
            ['humas', 'Anggota Humas', 'anggota.humas@demo.test'],
            ['humas', 'Anggota Humas', null],
        ];

        foreach ($members as $i => [$slug, $position, $email]) {
            $user = $email ? User::query()->where('email', $email)->first() : null;

            StructureMember::query()->create([
                'period_id' => $period->id,
                'division_id' => $divisions[$slug] ?? null,
                'user_id' => $user?->id,
                'name' => $user?->name ?? fake('id_ID')->name(),
                'position' => $position,
                'prodi' => $prodi[$i % count($prodi)],
                'sort_order' => $i,
            ]);
        }
    }

    /** @param  array<string, int>  $divisions */
    private function programs(Period $period, array $divisions): void
    {
        $programs = [
            'bph' => [['Rapat Kerja Kabinet', ProgramStatus::Done], ['Evaluasi Tengah Periode', ProgramStatus::Planned]],
            'kajian' => [['Diskusi Publik Bulanan', ProgramStatus::Ongoing], ['Kajian Kebijakan Kampus', ProgramStatus::Planned]],
            'medpub' => [['Desain Identitas Kabinet', ProgramStatus::Done], ['Konten Edukasi Mingguan', ProgramStatus::Ongoing]],
            'humas' => [['Kunjungan Antar-BEM', ProgramStatus::Planned], ['Forum Aspirasi Mahasiswa', ProgramStatus::Ongoing]],
        ];

        foreach ($programs as $slug => $items) {
            foreach ($items as $i => [$title, $status]) {
                Program::query()->create([
                    'division_id' => $divisions[$slug],
                    'period_id' => $period->id,
                    'title' => $title,
                    'description' => "Contoh deskripsi program {$title}.",
                    'status' => $status,
                    'starts_on' => now()->startOfMonth()->addMonths($i * 2 - 1),
                    'ends_on' => now()->startOfMonth()->addMonths($i * 2 - 1)->addDays(2),
                ]);
            }
        }
    }

    private function posts(User $author): void
    {
        $news = [
            ['Pelantikan Pengurus BEM Kabinet Contoh', PostCategory::Activity],
            ['Pembukaan Pendaftaran Lomba Esai Mahasiswa', PostCategory::Announcement],
            ['Mahasiswa UBHINUS Raih Juara Debat Regional', PostCategory::Achievement],
            ['Menjaga Ruang Diskusi Kampus Tetap Sehat', PostCategory::Opinion],
            ['Bakti Sosial BEM di Desa Binaan', PostCategory::Activity],
            ['Jadwal Layanan Sekretariat Selama Ujian', PostCategory::Announcement],
            ['Workshop Desain Konten untuk UKM', PostCategory::Activity],
        ];

        foreach ($news as $i => [$title, $category]) {
            $post = $this->makePost($author, PostType::Article, $title, $category, now()->subDays($i * 4 + 1));
            if ($i < 3) {
                $post->forceFill(['cover' => $this->demoImage('posts', 1600, 900, $i)])->save();
            }
        }

        $this->makePost($author, PostType::PressRelease, 'Pernyataan Sikap atas Kenaikan Biaya Praktikum', null, now()->subDays(3));
        $this->makePost($author, PostType::Study, 'Kajian: Transparansi Anggaran Kegiatan Mahasiswa', null, now()->subDays(10));
        $this->makePost($author, PostType::Study, 'Kajian: Aksesibilitas Fasilitas Kampus', null, now()->subDays(20));

        // Draft: tidak boleh tampil di website.
        Post::query()->forceCreate([
            'type' => PostType::Article,
            'title' => 'Contoh Draft yang Tidak Tampil',
            'slug' => 'contoh-draft-tidak-tampil',
            'body' => '<p>Konten ini masih draft.</p>',
            'status' => PostStatus::Draft,
            'author_id' => $author->id,
        ]);
    }

    private function makePost(User $author, PostType $type, string $title, ?PostCategory $category, $publishedAt): Post
    {
        $body = '<h2>Latar belakang</h2><p>'.implode('</p><p>', fake('id_ID')->paragraphs(2))
            .'</p><blockquote>Contoh kutipan dalam artikel.</blockquote><h2>Poin penting</h2>'
            .'<ul><li>Poin pertama</li><li>Poin kedua</li><li>Poin ketiga</li></ul><p>'
            .fake('id_ID')->paragraph().'</p>';

        return Post::query()->forceCreate([
            'type' => $type,
            'title' => $title,
            'slug' => 'contoh-'.Str::slug($title),
            'excerpt' => 'Contoh ringkasan: '.Str::lower($title).'.',
            'body' => $body,
            'category' => $category,
            'status' => PostStatus::Published,
            'author_id' => $author->id,
            'published_by' => $author->id,
            'published_at' => $publishedAt,
        ]);
    }

    private function galleries(): void
    {
        $images = app(ImageService::class);

        foreach (['Pelantikan Pengurus', 'Bakti Sosial Desa Binaan'] as $g => $title) {
            $gallery = Gallery::query()->create([
                'title' => $title,
                'slug' => 'contoh-'.Str::slug($title),
                'event_date' => now()->subDays(10 + $g * 15),
                'description' => "Contoh dokumentasi kegiatan {$title}.",
            ]);

            foreach (range(0, 3) as $i) {
                $tmp = $this->demoImageFile(1600, $i % 2 ? 1066 : 1200, $g * 4 + $i);
                $paths = $images->storeWithThumbnail($tmp, "galleries/{$gallery->id}");
                @unlink($tmp);

                GalleryPhoto::query()->create([
                    'gallery_id' => $gallery->id,
                    'path' => $paths['path'],
                    'thumb_path' => $paths['thumb_path'],
                    'caption' => 'Foto contoh '.($i + 1),
                    'sort_order' => $i,
                ]);
            }
        }
    }

    private function downloads(): void
    {
        $items = [
            ['Template Proposal Kegiatan', DownloadCategory::ProposalTemplate],
            ['Template LPJ Kegiatan', DownloadCategory::LpjTemplate],
            ['Form Peminjaman Barang Kampus', DownloadCategory::CampusLoanForm],
            ['Form Peminjaman Barang BEM', DownloadCategory::BemLoanForm],
            ['SOP Pengajuan Proposal', DownloadCategory::Sop],
        ];

        foreach ($items as [$title, $category]) {
            $path = Download::DIRECTORY.'/'.Str::uuid().'.pdf';
            $pdf = $this->minimalPdf($title);
            Storage::disk(Download::DISK)->put($path, $pdf);

            Download::query()->create([
                'title' => $title,
                'category' => $category,
                'description' => 'Contoh berkas untuk uji coba halaman unduhan.',
                'file_path' => $path,
                'original_name' => Str::slug($title).'.pdf',
                'mime_type' => 'application/pdf',
                'size' => strlen($pdf),
                'is_active' => true,
            ]);
        }
    }

    /** Gambar contoh (latar warna brand + logo) langsung disimpan sebagai WebP. */
    private function demoImage(string $directory, int $width, int $height, int $seed): string
    {
        $tmp = $this->demoImageFile($width, $height, $seed);
        $path = app(ImageService::class)->storeWebp($tmp, $directory);
        @unlink($tmp);

        return $path;
    }

    private function demoImageFile(int $width, int $height, int $seed): string
    {
        $manager = app(ImageService::class)->manager();
        $logo = $manager->decodePath(public_path('images/logo-bem.webp'))->scaleDown((int) ($height * 0.45), (int) ($height * 0.45));

        $tmp = tempnam(sys_get_temp_dir(), 'demo').'.png';
        $manager->createImage($width, $height)
            ->fill(self::COLORS[$seed % count(self::COLORS)])
            ->insert($logo, 0, 0, 'center', 0.35)
            ->encode(new PngEncoder)
            ->save($tmp);

        return $tmp;
    }

    /** PDF satu halaman yang valid, cukup untuk uji unduhan. */
    private function minimalPdf(string $title): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], 'Contoh: '.$title);
        $stream = "BT /F1 18 Tf 72 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
