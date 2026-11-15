<?php

use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Models\Division;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Models\Period;
use App\Models\Post;
use App\Models\StructureMember;

use function Pest\Laravel\get;

it('semua halaman publik dapat dibuka', function (string $url): void {
    get($url)->assertOk();
})->with([
    'beranda' => '/',
    'profil' => '/profil',
    'struktur' => '/struktur',
    'divisi' => '/divisi/kajian',
    'berita' => '/berita',
    'kajian' => '/kajian',
    'proker' => '/proker',
    'galeri' => '/galeri',
    'unduhan' => '/unduhan',
    'kalender' => '/kalender',
    'kontak' => '/kontak',
]);

it('halaman layanan yang belum dibuat menampilkan "segera hadir"', function (string $url): void {
    get($url)->assertOk()->assertSee('Segera hadir')->assertSee('noindex', false);
})->with(['/pengajuan', '/pengajuan/status', '/oprec', '/oprec/hasil']);

it('divisi yang tidak ada menghasilkan 404 bergaya situs', function (): void {
    get('/divisi/tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan');
});

it('hanya menampilkan berita yang sudah terbit', function (): void {
    $published = Post::factory()->published()->create(['title' => 'Berita Sudah Terbit']);
    $hidden = collect(PostStatus::cases())
        ->reject(fn (PostStatus $s) => $s === PostStatus::Published)
        ->map(fn (PostStatus $s) => Post::factory()->status($s)->create(['title' => 'Rahasia '.$s->value]));
    $future = Post::factory()->published(now()->addDay())->create(['title' => 'Terjadwal Besok']);

    $response = get('/berita')->assertOk()->assertSee('Berita Sudah Terbit')->assertDontSee('Terjadwal Besok');
    $hidden->each(fn (Post $p) => $response->assertDontSee($p->title));

    get("/berita/{$published->slug}")->assertOk()->assertSee('Berita Sudah Terbit');
    get("/berita/{$future->slug}")->assertNotFound();
    $hidden->each(fn (Post $p) => get("/berita/{$p->slug}")->assertNotFound());

    // Pencarian & beranda juga tidak membocorkan draft.
    get('/berita?q=Rahasia')->assertOk()->assertDontSee('Rahasia draft');
    get('/')->assertOk()->assertSee('Berita Sudah Terbit')->assertDontSee('Rahasia draft');
});

it('draft tidak masuk sitemap, berita terbit masuk', function (): void {
    $published = Post::factory()->published()->create();
    $draft = Post::factory()->create();

    get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('public.news.show', $published->slug), false)
        ->assertDontSee($draft->slug, false);
});

it('filter kategori dan pencarian berita bekerja', function (): void {
    Post::factory()->published()->create(['title' => 'Juara Lomba Debat', 'category' => 'achievement']);
    Post::factory()->published()->create(['title' => 'Bakti Sosial Desa', 'category' => 'activity']);

    get('/berita?kategori=prestasi')->assertSee('Juara Lomba Debat')->assertDontSee('Bakti Sosial Desa');
    get('/berita?q=bakti')->assertSee('Bakti Sosial Desa')->assertDontSee('Juara Lomba Debat');
    // Karakter wildcard tidak membuat pencarian mengembalikan semua data.
    get('/berita?q=%25')->assertDontSee('Bakti Sosial Desa');
});

it('kajian hanya berisi press release dan kajian publik', function (): void {
    $study = Post::factory()->type(PostType::Study)->published()->create(['title' => 'Kajian Anggaran']);
    Post::factory()->type(PostType::PressRelease)->published()->create(['title' => 'Pernyataan Sikap']);
    $article = Post::factory()->type(PostType::Article)->published()->create(['title' => 'Artikel Biasa']);

    get('/kajian')->assertSee('Kajian Anggaran')->assertSee('Pernyataan Sikap')->assertDontSee('Artikel Biasa');
    get('/kajian?jenis=press-release')->assertSee('Pernyataan Sikap')->assertDontSee('Kajian Anggaran');
    get("/kajian/{$study->slug}")->assertOk();
    get("/kajian/{$article->slug}")->assertNotFound();
    get('/berita')->assertDontSee('Kajian Anggaran');
});

it('isi berita disanitasi: script dan atribut berbahaya dibuang', function (): void {
    $post = Post::factory()->published()->create([
        'body' => '<p>Aman</p><script>alert("xss")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(2)">klik</a>',
    ]);

    expect($post->fresh()->body)->not->toContain('<script')->not->toContain('onerror')->not->toContain('javascript:');

    get("/berita/{$post->slug}")
        ->assertOk()
        ->assertSee('Aman')
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertDontSee('onerror=', false)
        ->assertDontSee('javascript:alert', false);
});

it('halaman berita memuat meta SEO dan Open Graph', function (): void {
    $post = Post::factory()->published()->create(['title' => 'Judul Untuk SEO', 'excerpt' => 'Ringkasan untuk mesin pencari.']);

    get("/berita/{$post->slug}")
        ->assertSee('<title>Judul Untuk SEO — BEM UBHINUS</title>', false)
        ->assertSee('<meta name="description" content="Ringkasan untuk mesin pencari.">', false)
        ->assertSee('<meta property="og:title" content="Judul Untuk SEO">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<link rel="canonical"', false);
});

it('robots.txt memblokir pengindeksan di luar produksi dan memblokir panel di produksi', function (): void {
    get('/robots.txt')->assertOk()->assertSee('Disallow: /');

    app()->detectEnvironment(fn () => 'production');
    get('/robots.txt')->assertOk()->assertSee('Disallow: /panel-bem')->assertSee('Sitemap: '.route('sitemap'));
});

it('struktur hanya menampilkan anggota periode aktif', function (): void {
    $active = Period::factory()->active()->create();
    $old = Period::factory()->create();
    $kajian = Division::where('slug', 'kajian')->first();

    StructureMember::factory()->create(['period_id' => $active->id, 'division_id' => $kajian->id, 'name' => 'Pengurus Aktif']);
    StructureMember::factory()->create(['period_id' => $old->id, 'division_id' => $kajian->id, 'name' => 'Pengurus Lama']);

    get('/struktur')->assertOk()->assertSee('Pengurus Aktif')->assertDontSee('Pengurus Lama');
    get('/divisi/kajian')->assertOk()->assertSee('Pengurus Aktif')->assertDontSee('Pengurus Lama');
});

it('album tanpa foto tidak tampil', function (): void {
    $empty = Gallery::factory()->create(['title' => 'Album Kosong']);
    $filled = Gallery::factory()->create(['title' => 'Album Berisi']);
    GalleryPhoto::query()->create(['gallery_id' => $filled->id, 'path' => 'galleries/x.webp', 'thumb_path' => 'galleries/thumbs/x.webp']);

    get('/galeri')->assertSee('Album Berisi')->assertDontSee('Album Kosong');
    get("/galeri/{$empty->slug}")->assertNotFound();
    get("/galeri/{$filled->slug}")->assertOk();
});
