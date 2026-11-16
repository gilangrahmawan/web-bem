<?php

use App\Enums\DriveAccess;
use App\Enums\DriveGranteeType;
use App\Enums\RoleName;
use App\Filament\Pages\Drive;
use App\Models\Document;
use App\Models\DriveFile;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Services\Drive\DriveAccessResolver;
use App\Services\Drive\DriveService;
use App\Services\Drive\SystemFolders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('local'));

function driveShare(Folder $folder, DriveGranteeType $type, int $id, DriveAccess $access): FolderPermission
{
    return FolderPermission::query()->create([
        'folder_id' => $folder->id, 'grantee_type' => $type->value, 'grantee_id' => $id, 'access' => $access->value,
    ]);
}

function drive(): DriveService
{
    return app(DriveService::class);
}

it('Sekretaris & Super Admin kelola semua, Pembina hanya lihat, anggota tanpa izin tidak melihat apa pun', function (): void {
    $folder = Folder::factory()->create();
    $resolver = app(DriveAccessResolver::class);

    expect($resolver->accessFor(member(RoleName::Sekretaris), $folder))->toBe(DriveAccess::Manage)
        ->and($resolver->accessFor(superAdmin(), $folder))->toBe(DriveAccess::Manage)
        ->and($resolver->accessFor(superAdmin(), null))->toBe(DriveAccess::Manage)
        ->and($resolver->accessFor(member(RoleName::Pembina), $folder))->toBe(DriveAccess::View)
        ->and($resolver->accessFor(member(RoleName::AnggotaKajian), $folder))->toBeNull()
        ->and($resolver->accessFor(member(RoleName::Bendahara), null))->toBeNull();
});

it('izin folder diwariskan ke seluruh subfolder (role, divisi, user) dan akses tertinggi yang berlaku', function (): void {
    $root = Folder::factory()->create(['name' => 'Kajian']);
    $sub = Folder::factory()->in($root)->create();
    $deep = Folder::factory()->in($sub)->create();
    $other = Folder::factory()->create();

    $anggota = member(RoleName::AnggotaKajian);
    $koor = member(RoleName::KoordinatorKajian);
    $humas = member(RoleName::AnggotaHumas);

    driveShare($root, DriveGranteeType::Division, divisionId('kajian'), DriveAccess::View);
    driveShare($sub, DriveGranteeType::Role, Role::findByName('koordinator_kajian')->id, DriveAccess::Manage);
    driveShare($deep, DriveGranteeType::User, $anggota->id, DriveAccess::Upload);

    $r = app(DriveAccessResolver::class);
    expect($r->accessFor($anggota, $root))->toBe(DriveAccess::View)
        ->and($r->accessFor($anggota, $sub))->toBe(DriveAccess::View)
        ->and($r->accessFor($anggota, $deep))->toBe(DriveAccess::Upload)
        ->and($r->accessFor($koor, $root))->toBe(DriveAccess::View)
        ->and($r->accessFor($koor, $deep))->toBe(DriveAccess::Manage)
        ->and($r->accessFor($anggota, $other))->toBeNull()
        ->and($r->accessFor($humas, $deep))->toBeNull()
        ->and($r->sharedRootIds($anggota))->toBe([$root->id]);
});

it('berkas tidak bisa diunduh, dipratinjau, atau di-thumbnail tanpa izin walau menebak ID (404)', function (): void {
    $secret = Folder::factory()->create();
    $file = DriveFile::factory()->create(['folder_id' => $secret->id]);
    $document = Document::factory()->create(['folder_id' => $secret->id]);

    foreach ([RoleName::AnggotaMedpub, RoleName::KoordinatorHumas, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get(route('panel.drive.files.download', $file))->assertNotFound();
        get(route('panel.drive.files.preview', $file))->assertNotFound();
        get(route('panel.drive.files.thumbnail', $file))->assertNotFound();
        get(route('panel.drive.folders.zip', $secret))->assertNotFound();
        get(route('panel.drive.documents.export', [$document, 'pdf']))->assertNotFound();
    }

    $medpub = member(RoleName::AnggotaMedpub);
    driveShare($secret, DriveGranteeType::User, $medpub->id, DriveAccess::View);
    actingAs($medpub);
    get(route('panel.drive.files.download', $file))->assertOk()->assertDownload($file->original_name);
    get(route('panel.drive.files.preview', $file))->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Berkas di Sampah tidak bisa diunduh siapa pun lewat route biasa.
    drive()->trash(member(RoleName::Sekretaris), $file);
    actingAs(member(RoleName::Sekretaris));
    get(route('panel.drive.files.download', $file->id))->assertNotFound();
});

it('akses unggah: hanya mengubah/membuang item miliknya; kelola: semua item', function (): void {
    $folder = Folder::factory()->create();
    $uploader = member(RoleName::AnggotaHumas);
    $manager = member(RoleName::KoordinatorHumas);
    driveShare($folder, DriveGranteeType::User, $uploader->id, DriveAccess::Upload);
    driveShare($folder, DriveGranteeType::User, $manager->id, DriveAccess::Manage);

    $mine = drive()->createFolder($uploader, $folder->id, 'Milik saya');
    $theirs = DriveFile::factory()->create(['folder_id' => $folder->id, 'uploaded_by' => $manager->id]);

    drive()->rename($uploader, $mine, 'Milik saya (baru)');
    expect($mine->refresh()->name)->toBe('Milik saya (baru)');

    expect(fn () => drive()->rename($uploader, $theirs, 'Diambil alih'))->toThrow(AuthorizationException::class);
    expect(fn () => drive()->trash($uploader, $theirs))->toThrow(AuthorizationException::class);

    drive()->rename($manager, $mine, 'Dirapikan koordinator');
    drive()->trash($manager, $theirs);
    expect($theirs->refresh()->trashed())->toBeTrue();

    // Akses lihat saja tidak bisa menambah item.
    $viewer = member(RoleName::AnggotaKajian);
    driveShare($folder, DriveGranteeType::User, $viewer->id, DriveAccess::View);
    expect(fn () => drive()->createFolder($viewer, $folder->id, 'Coba'))->toThrow(AuthorizationException::class);

    // Bagikan hanya untuk pemegang akses kelola.
    expect(fn () => drive()->share($uploader, $folder, DriveGranteeType::User, $viewer->id, DriveAccess::Upload))->toThrow(AuthorizationException::class);
    drive()->share($manager, $folder, DriveGranteeType::User, $viewer->id, DriveAccess::Upload);
    expect(app(DriveAccessResolver::class)->accessFor($viewer, $folder))->toBe(DriveAccess::Upload);
});

it('Pembina tidak bisa membuat, mengubah, membagikan, atau membuang apa pun', function (): void {
    $folder = Folder::factory()->create();
    $file = DriveFile::factory()->create(['folder_id' => $folder->id]);
    $pembina = member(RoleName::Pembina);

    expect(fn () => drive()->createFolder($pembina, $folder->id, 'X'))->toThrow(AuthorizationException::class);
    expect(fn () => drive()->rename($pembina, $file, 'X'))->toThrow(AuthorizationException::class);
    expect(fn () => drive()->trash($pembina, $file))->toThrow(AuthorizationException::class);
    expect(fn () => drive()->share($pembina, $folder, DriveGranteeType::User, $pembina->id, DriveAccess::View))->toThrow(AuthorizationException::class);

    actingAs($pembina);
    get(route('panel.drive.files.download', $file))->assertOk();
});

it('folder sistem "Surat" terkunci (juga bagi Super Admin) dengan izin default Humas', function (): void {
    $surat = app(SystemFolders::class)->letters();
    $admin = superAdmin();

    expect(fn () => drive()->rename($admin, $surat, 'Ganti'))->toThrow(AuthorizationException::class);
    expect(fn () => drive()->trash($admin, $surat))->toThrow(AuthorizationException::class);
    expect(fn () => drive()->move($admin, $surat, Folder::factory()->create()->id))->toThrow(AuthorizationException::class);

    $r = app(DriveAccessResolver::class);
    expect($r->accessFor(member(RoleName::AnggotaHumas), $surat))->toBe(DriveAccess::View)
        ->and($r->accessFor(member(RoleName::KoordinatorHumas), $surat))->toBe(DriveAccess::Upload)
        ->and($r->accessFor(member(RoleName::AnggotaKajian), $surat))->toBeNull();

    // Isi folder sistem tetap bisa dikelola biasa.
    $sub = drive()->createFolder($admin, $surat->id, 'Surat masuk');
    expect($sub->parent_id)->toBe($surat->id);

    // Idempoten: tidak membuat duplikat atau izin ganda.
    app(SystemFolders::class)->letters();
    expect(Folder::where('system_key', 'letters')->count())->toBe(1)
        ->and(FolderPermission::where('folder_id', $surat->id)->count())->toBe(2);
});

it('pengaju kegiatan tidak bisa membuka Drive dan tidak bisa menerima izin folder', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan);
    $folder = Folder::factory()->create();

    actingAs($pengaju);
    get(Drive::getUrl())->assertForbidden();
    expect(app(DriveAccessResolver::class)->canUseDrive($pengaju))->toBeFalse();

    // Walau ada baris izin langsung (mis. dimanipulasi), resolver tetap menolak.
    driveShare($folder, DriveGranteeType::User, $pengaju->id, DriveAccess::Manage);
    expect(app(DriveAccessResolver::class)->accessFor($pengaju, $folder))->toBeNull();

    $sekretaris = member(RoleName::Sekretaris);
    expect(fn () => drive()->share($sekretaris, $folder, DriveGranteeType::User, member(RoleName::PengajuKegiatan)->id, DriveAccess::View))
        ->toThrow(ValidationException::class);
    expect(fn () => drive()->share($sekretaris, $folder, DriveGranteeType::Role, Role::findByName('pengaju_kegiatan')->id, DriveAccess::View))
        ->toThrow(ValidationException::class);
});

it('user nonaktif kehilangan seluruh akses Drive', function (): void {
    $folder = Folder::factory()->create();
    $user = member(RoleName::AnggotaKajian, ['is_active' => false]);
    driveShare($folder, DriveGranteeType::User, $user->id, DriveAccess::Manage);

    expect(app(DriveAccessResolver::class)->accessFor($user, $folder))->toBeNull();
});
