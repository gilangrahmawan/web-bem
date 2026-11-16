<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\Download;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Models\Organization;
use App\Models\Period;
use App\Models\Post;
use App\Models\Program;
use App\Models\Setting;
use App\Models\StructureMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->zip = tempnam(sys_get_temp_dir(), 'bemdata').'.zip';
});

afterEach(fn () => @unlink($this->zip));

/** Data lokal: campuran data asli + data demo yang TIDAK boleh ikut. */
function seedLocalData(): void
{
    Setting::set('cabinet_name', 'Kabinet Harmoni');
    Setting::set('vision', 'Visi asli');
    Setting::set('kas_start_date', '2026-11-02');
    Storage::disk('public')->put('site/hero.webp', 'hero');
    Setting::set('hero_image', 'site/hero.webp');

    $real = Period::factory()->create(['name' => 'Kabinet Harmoni 2026/2027', 'is_active' => true]);
    $demoPeriod = Period::factory()->create(['name' => 'Kabinet Contoh 2026/2027', 'is_active' => false]);

    Storage::disk('public')->put('structure/ketua.webp', 'foto');
    StructureMember::factory()->create(['period_id' => $real->id, 'division_id' => divisionId('bph'), 'name' => 'Rani Ketua', 'position' => 'Ketua Umum', 'photo' => 'structure/ketua.webp']);
    $demoUser = User::factory()->create(['email' => 'anggota.kajian@demo.test']);
    StructureMember::factory()->create(['period_id' => $real->id, 'division_id' => divisionId('kajian'), 'user_id' => $demoUser->id, 'name' => 'Akun Demo']);
    StructureMember::factory()->create(['period_id' => $demoPeriod->id, 'name' => 'Pengurus Contoh']);

    Program::factory()->create(['period_id' => $real->id, 'division_id' => divisionId('humas'), 'title' => 'Sapa Mahasiswa']);
    Program::factory()->create(['period_id' => $demoPeriod->id, 'division_id' => divisionId('humas'), 'title' => 'Proker Contoh']);

    Event::factory()->create(['title' => 'Rapat Kerja BEM', 'category' => EventCategory::Bem, 'starts_at' => '2026-12-05 08:00:00']);
    Event::factory()->create(['title' => 'Contoh: Turnamen Futsal', 'category' => EventCategory::Ukm]);
    Event::factory()->create(['title' => 'Poster Hari Ibu', 'category' => EventCategory::Content]);

    Organization::factory()->create(['name' => 'HMJ Informatika']);
    $demoOrg = Organization::factory()->create(['name' => 'UKM Futsal Demo']);
    member(RoleName::PengajuKegiatan, ['email' => 'pengaju.futsal@demo.test', 'organization_id' => $demoOrg->id]);

    Storage::disk('public')->put('posts/covers/a.webp', 'cover');
    Post::factory()->published()->create(['title' => 'Pelantikan Kabinet Harmoni', 'slug' => 'pelantikan-kabinet-harmoni', 'cover' => 'posts/covers/a.webp']);
    Post::factory()->create(['title' => 'Draft Belum Terbit', 'slug' => 'draft-belum-terbit']);
    Post::factory()->published()->create(['title' => 'Berita Contoh', 'slug' => 'contoh-berita']);

    $gallery = Gallery::factory()->create(['title' => 'Makrab 2026', 'slug' => 'makrab-2026']);
    Storage::disk('public')->put('galleries/1/p.webp', 'p');
    Storage::disk('public')->put('galleries/1/thumbs/p.webp', 't');
    GalleryPhoto::query()->create(['gallery_id' => $gallery->id, 'path' => 'galleries/1/p.webp', 'thumb_path' => 'galleries/1/thumbs/p.webp', 'caption' => 'Foto bersama']);
    Gallery::factory()->create(['title' => 'Galeri Contoh', 'slug' => 'contoh-galeri']);

    Storage::disk('local')->put('downloads/template.pdf', '%PDF');
    Download::factory()->create(['title' => 'Template Proposal', 'description' => 'Template resmi', 'file_path' => 'downloads/template.pdf']);
    Download::factory()->create(['title' => 'Unduhan Contoh', 'description' => 'Contoh berkas untuk uji coba halaman unduhan.']);
}

/** Simulasi server produksi: kosongkan data yang akan dipindah (akun tetap). */
function wipeTransferableData(): void
{
    foreach (['gallery_photos', 'galleries', 'downloads', 'posts', 'programs', 'structure_members', 'event_assignees', 'events', 'periods'] as $table) {
        DB::table($table)->delete();
    }
    DB::table('settings')->whereIn('key', ['cabinet_name', 'vision', 'hero_image', 'kas_start_date'])->delete();
    Setting::flushCache();
    Storage::fake('public');
    Storage::fake('local');
}

it('export hanya memuat data asli: tanpa akun, data demo, draft, dan setting teknis', function (): void {
    seedLocalData();

    $this->artisan('bem:export-data', ['--output' => $this->zip])->assertSuccessful();

    $zip = new ZipArchive;
    $zip->open($this->zip);
    $json = $zip->getFromName('data.json');
    $data = json_decode($json, true);
    $files = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
    $zip->close();

    expect($json)->not->toContain('@demo.test')->not->toContain('Contoh')->not->toContain('contoh-')
        ->not->toContain('kas_start_date')->not->toContain('Draft Belum Terbit')->not->toContain('Poster Hari Ibu')
        ->not->toContain('UKM Futsal Demo')->not->toContain('"password"')
        ->and(collect($data['tables']['settings'])->pluck('key')->all())->toContain('cabinet_name', 'hero_image')
        ->and($data['tables']['structure_members'])->toHaveCount(1)
        ->and($data['tables']['posts'])->toHaveCount(1)
        ->and($files)->toContain('files/public/structure/ketua.webp', 'files/public/posts/covers/a.webp', 'files/public/site/hero.webp',
            'files/public/galleries/1/p.webp', 'files/local/downloads/template.pdf');
});

it('import --dry-run tidak menyimpan apa pun; import menyimpan data + berkas; import ulang tidak menggandakan', function (): void {
    seedLocalData();
    $this->artisan('bem:export-data', ['--output' => $this->zip])->assertSuccessful();
    $usersBefore = User::count();

    wipeTransferableData();

    $ketua = superAdmin(); // urutan go-live: bem:create-admin dulu, baru import
    $this->artisan('bem:import-data', ['path' => $this->zip, '--dry-run' => true])->expectsOutputToContain('DRY RUN')->assertSuccessful();
    expect(Period::count())->toBe(0)->and(Post::count())->toBe(0);
    Storage::disk('public')->assertMissing('structure/ketua.webp');

    $this->artisan('bem:import-data', ['path' => $this->zip, '--force' => true])->assertSuccessful();

    expect(Setting::get('cabinet_name'))->toBe('Kabinet Harmoni')
        ->and(Setting::get('kas_start_date'))->toBeNull()
        ->and(Period::where('is_active', true)->sole()->name)->toBe('Kabinet Harmoni 2026/2027')
        ->and(StructureMember::sole()->only(['name', 'user_id']))->toBe(['name' => 'Rani Ketua', 'user_id' => null])
        ->and(Program::sole()->title)->toBe('Sapa Mahasiswa')
        ->and(Event::pluck('title')->all())->toBe(['Rapat Kerja BEM'])
        ->and(Post::sole()->only(['slug', 'author_id']))->toBe(['slug' => 'pelantikan-kabinet-harmoni', 'author_id' => $ketua->id])
        ->and(Gallery::sole()->photos)->toHaveCount(1)
        ->and(Download::sole()->title)->toBe('Template Proposal')
        ->and(User::count())->toBe($usersBefore + 1);

    Storage::disk('public')->assertExists(['structure/ketua.webp', 'posts/covers/a.webp', 'site/hero.webp', 'galleries/1/p.webp']);
    Storage::disk('local')->assertExists('downloads/template.pdf');

    // Idempoten.
    $this->artisan('bem:import-data', ['path' => $this->zip, '--force' => true])->assertSuccessful();
    expect(StructureMember::count())->toBe(1)->and(Event::count())->toBe(1)->and(Post::count())->toBe(1)
        ->and(GalleryPhoto::count())->toBe(1)->and(Organization::where('name', 'HMJ Informatika')->count())->toBe(1);
});

it('paket asing atau rusak ditolak tanpa perubahan', function (): void {
    $zip = new ZipArchive;
    $zip->open($this->zip, ZipArchive::CREATE);
    $zip->addFromString('data.json', json_encode(['format' => 'lain']));
    $zip->addFromString('files/public/../../.env', 'jahat');
    $zip->close();

    $this->artisan('bem:import-data', ['path' => $this->zip, '--force' => true])->expectsOutputToContain('Format paket tidak dikenali')->assertFailed();
});
