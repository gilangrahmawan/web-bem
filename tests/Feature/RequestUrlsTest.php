<?php

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

/*
| URL absolut per environment (uji dari HP lewat http://IP-LAPTOP:8000):
| lokal → mengikuti host yang diakses; produksi → dikunci ke APP_URL + HTTPS.
*/

afterEach(function (): void {
    @unlink(public_path('hot'));
    $this->app['env'] = 'testing';
});

function postWithCover(): Post
{
    Storage::disk('public')->put('posts/sampul-uji.webp', 'img');

    return Post::factory()->published()->create(['cover' => 'posts/sampul-uji.webp']);
}

it('LOKAL: halaman via IP laptop memuat aset Vite, favicon, manifest, dan foto dari host itu (bukan APP_URL)', function (): void {
    if (! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Jalankan npm run build terlebih dahulu.');
    }

    $this->app['env'] = 'local';
    $this->withVite();
    config(['app.url' => 'http://bemweb.test']);
    postWithCover();

    $html = get('http://192.168.1.20:8000/')->assertOk()->getContent();

    expect($html)->toContain('http://192.168.1.20:8000/build/assets/')
        ->toContain('http://192.168.1.20:8000/favicon.ico')
        ->toContain('http://192.168.1.20:8000/storage/posts/sampul-uji.webp')
        ->not->toContain('http://bemweb.test');

    // Panel (Filament + Livewire) dan manifest PWA juga mengikuti host.
    $login = get('http://192.168.1.20:8000/panel-bem/login')->assertOk()->getContent();
    expect($login)->toContain('http://192.168.1.20:8000/')->not->toContain('http://bemweb.test');
});

it('LOKAL: berkas public/hot (npm run dev) diabaikan bila server Vite tidak bisa dijangkau dari host yang diakses', function (): void {
    if (! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Jalankan npm run build terlebih dahulu.');
    }

    $this->app['env'] = 'local';
    $this->withVite();
    file_put_contents(public_path('hot'), 'http://bemweb.test:5173');

    expect(get('http://192.168.1.20:8000/')->getContent())
        ->toContain('http://192.168.1.20:8000/build/assets/')
        ->not->toContain('bemweb.test:5173');

    // Dibuka dari host yang sama dengan server Vite → hot reload tetap dipakai.
    expect(get('http://bemweb.test/')->getContent())->toContain('http://bemweb.test:5173');
});

it('PRODUKSI: semua URL tetap memakai APP_URL + HTTPS walau Host header berbeda', function (): void {
    $this->app['env'] = 'production';
    config([
        'app.url' => 'https://bem.ubhinus.ac.id',
        'filesystems.disks.public.url' => 'https://bem.ubhinus.ac.id/storage',
    ]);
    Storage::forgetDisk('public');
    postWithCover();

    $html = get('http://192.168.1.20:8000/')->assertOk()->getContent();

    expect($html)->toContain('https://bem.ubhinus.ac.id/favicon.ico')
        ->toContain('https://bem.ubhinus.ac.id/storage/posts/sampul-uji.webp')
        ->toContain('<link rel="canonical" href="https://bem.ubhinus.ac.id"')
        ->not->toContain('192.168.1.20');
});
