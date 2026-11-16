<?php

use App\Enums\RoleName;
use App\Filament\Pages\Drive;
use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Filament\Widgets\DriveActivityWidget;
use App\Filament\Widgets\DriveFileTypesChart;
use App\Filament\Widgets\DriveStorageWidget;
use App\Models\Document;
use App\Models\DriveFile;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Services\Drive\DriveService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Storage::fake('local');
    $GLOBALS['drivePageTemps'] = [];
});

afterEach(function (): void {
    foreach ($GLOBALS['drivePageTemps'] ?? [] as $path) {
        @unlink($path);
    }
});

function pageUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'drp');
    file_put_contents($path, $content);
    $GLOBALS['drivePageTemps'][] = $path;

    return new UploadedFile($path, $name, null, null, true);
}

it('Sekretaris membuka Drive: folder sistem Surat dibuat, folder baru & upload dari halaman', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    actingAs($sekretaris);

    get(Drive::getUrl())->assertOk()->assertSee('Selamat datang di Drive')->assertSee('Surat');
    expect(Folder::where('system_key', 'letters')->exists())->toBeTrue();

    livewire(Drive::class)->call('go', 'my')
        ->callAction('createFolder', ['name' => 'Arsip Proker'])
        ->assertHasNoActionErrors();
    $folder = Folder::where('name', 'Arsip Proker')->sole();

    $page = livewire(Drive::class, ['section' => 'folder', 'folderId' => $folder->id])
        ->set('upload', UploadedFile::fake()->createWithContent('jadwal.pdf', "%PDF-1.4\n".str_repeat('x', 2000)));
    expect($page->instance()->storeUpload())->toMatchArray(['ok' => true, 'name' => 'jadwal.pdf']);
    expect(DriveFile::where('folder_id', $folder->id)->sole()->original_name)->toBe('jadwal.pdf');

    // Di test Livewire MIME diambil dari metadata berkas tiruan (di produksi: dideteksi dari isi,
    // diuji dengan berkas sungguhan di DriveServiceTest).
    $page->set('upload', UploadedFile::fake()->create('skrip.pdf', 1, 'text/x-php'));
    expect($page->instance()->storeUpload())->toMatchArray(['ok' => false]);
    expect(DriveFile::count())->toBe(1);

    livewire(Drive::class, ['section' => 'folder', 'folderId' => $folder->id])->assertSee('jadwal.pdf');
});

it('anggota hanya melihat folder yang dibagikan; folder lain lewat URL kembali ke Beranda', function (): void {
    $anggota = member(RoleName::AnggotaKajian);
    $shared = Folder::factory()->create(['name' => 'Bahan Kajian']);
    $secret = Folder::factory()->create(['name' => 'Rahasia BPH']);
    FolderPermission::create(['folder_id' => $shared->id, 'grantee_type' => 'division', 'grantee_id' => divisionId('kajian'), 'access' => 'view']);
    DriveFile::factory()->create(['folder_id' => $secret->id, 'original_name' => 'gaji-panitia.pdf']);

    actingAs($anggota);
    livewire(Drive::class)->call('go', 'shared')->assertSee('Bahan Kajian')->assertDontSee('Rahasia BPH');

    livewire(Drive::class, ['section' => 'folder', 'folderId' => $secret->id])
        ->assertSet('section', 'home')->assertSet('folderId', null)->assertDontSee('gaji-panitia.pdf');

    livewire(Drive::class)->set('search', 'gaji')->assertDontSee('gaji-panitia.pdf');

    // Akses lihat: tidak bisa membuat folder di folder bersama.
    livewire(Drive::class, ['section' => 'folder', 'folderId' => $shared->id])
        ->assertActionHidden('createFolder')
        ->assertActionHidden(TestAction::make('rename')->arguments(['type' => 'folder', 'id' => $shared->id]))
        ->assertActionHidden(TestAction::make('trash')->arguments(['type' => 'folder', 'id' => $shared->id]));
});

it('buang lalu pulihkan lewat halaman; bintang muncul di Berbintang', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $file = DriveFile::factory()->create(['original_name' => 'laporan.pdf']);
    actingAs($sekretaris);

    livewire(Drive::class)->call('toggleStar', 'file', $file->id)->call('go', 'starred')->assertSee('laporan.pdf');

    livewire(Drive::class)->call('go', 'my')
        ->callAction(TestAction::make('trash')->arguments(['type' => 'file', 'id' => $file->id]));
    expect($file->fresh()->trashed())->toBeTrue();

    livewire(Drive::class)->call('go', 'trash')->assertSee('laporan.pdf')
        ->callAction(TestAction::make('restore')->arguments(['type' => 'file', 'id' => $file->id]));
    expect($file->fresh()->trashed())->toBeFalse();
});

it('dokumen baru dari halaman Drive membuka editor', function (): void {
    actingAs(member(RoleName::Sekretaris));

    livewire(Drive::class)->call('go', 'my')
        ->callAction('createDocument', ['title' => 'Rencana Kerja'])
        ->assertRedirect();

    expect(Document::where('title', 'Rencana Kerja')->exists())->toBeTrue();
});

it('widget Drive mengikuti hak akses: penyimpanan untuk pengelola & Pembina; jenis & aktivitas hanya item terlihat', function (): void {
    $shared = Folder::factory()->create();
    $secret = Folder::factory()->create();
    FolderPermission::create(['folder_id' => $shared->id, 'grantee_type' => 'division', 'grantee_id' => divisionId('medpub'), 'access' => 'upload']);
    app(DriveService::class)->createFolder(member(RoleName::Sekretaris), $secret->id, 'Folder-Rahasia-Sekali');

    foreach ([RoleName::Sekretaris, RoleName::Pembina] as $role) {
        actingAs(member($role));
        expect(DriveStorageWidget::canView())->toBeTrue();
        livewire(DriveStorageWidget::class)->assertSee('Terpakai');
    }

    actingAs(member(RoleName::AnggotaMedpub));
    expect(DriveStorageWidget::canView())->toBeFalse()
        ->and(DriveFileTypesChart::canView())->toBeTrue();
    livewire(DriveFileTypesChart::class)->assertSee('Belum ada berkas');
    livewire(DriveActivityWidget::class)->assertDontSee('Folder-Rahasia-Sekali');

    foreach ([RoleName::AnggotaKajian, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        expect(DriveFileTypesChart::canView())->toBeFalse()
            ->and(DriveActivityWidget::canView())->toBeFalse()
            ->and(DriveStorageWidget::canView())->toBeFalse();
    }

    actingAs(member(RoleName::Sekretaris));
    livewire(DriveActivityWidget::class)->assertSee('Folder-Rahasia-Sekali');
});

it('template dokumen hanya untuk Sekretaris & Super Admin', function (): void {
    actingAs(member(RoleName::Sekretaris));
    get(DocumentTemplateResource::getUrl('index'))->assertOk()->assertSee('Notulen Rapat');

    foreach ([RoleName::AnggotaHumas, RoleName::Pembina, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get(DocumentTemplateResource::getUrl('index'))->assertForbidden();
    }
});

it('halaman Drive tampil rapi untuk Pembina (lihat semua) dan anggota tanpa folder bersama (kosong)', function (): void {
    Folder::factory()->create(['name' => 'Arsip Kabinet']);

    actingAs(member(RoleName::Pembina));
    get(Drive::getUrl(['bagian' => 'my']))->assertOk()->assertSee('Arsip Kabinet');

    actingAs(member(RoleName::Bendahara));
    get(Drive::getUrl(['bagian' => 'shared']))->assertOk()->assertSee('Belum ada folder yang dibagikan kepada Anda')->assertDontSee('Arsip Kabinet');
});
