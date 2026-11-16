<?php

use App\Enums\DriveAccess;
use App\Enums\DriveFileKind;
use App\Enums\DriveGranteeType;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DriveActivity;
use App\Models\DriveFile;
use App\Models\DriveStar;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\Setting;
use App\Services\Drive\DriveService;
use App\Services\Drive\DriveThumbnailer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Storage::fake('local');
    $GLOBALS['driveTemps'] = [];
});

afterEach(function (): void {
    foreach ($GLOBALS['driveTemps'] ?? [] as $path) {
        @unlink($path);
    }
});

/** Berkas "sungguhan": MIME dideteksi dari isi (finfo), bukan ekstensi. */
function driveUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'drv');
    file_put_contents($path, $content);
    $GLOBALS['driveTemps'][] = $path;

    return new UploadedFile($path, $name, null, null, true);
}

function drivePdf(int $kb = 20): string
{
    return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n".str_repeat('x', $kb * 1024)."\n%%EOF\n";
}

function drivePng(int $w = 900, int $h = 600): string
{
    $im = imagecreatetruecolor($w, $h);
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

function svc(): DriveService
{
    return app(DriveService::class);
}

it('upload menyimpan berkas ASLI bernama UUID di disk privat + mencatat aktivitas', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $folder = Folder::factory()->create();
    $content = drivePdf();

    $file = svc()->upload($sekretaris, $folder->id, driveUpload('Proposal Final.pdf', $content));

    expect($file->original_name)->toBe('Proposal Final.pdf')
        ->and($file->stored_name)->toMatch('/^[0-9a-f-]{36}\.pdf$/')
        ->and($file->kind)->toBe(DriveFileKind::Pdf)
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->uploaded_by)->toBe($sekretaris->id);

    expect(Storage::disk('local')->get($file->path()))->toBe($content);
    Storage::disk('public')->assertMissing($file->path());
    expect(DriveActivity::where('action', 'upload')->where('item_id', $file->id)->exists())->toBeTrue();
});

it('gambar disimpan asli + thumbnail WebP; PDF tanpa Imagick memakai ikon (thumbnail null)', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $png = drivePng();

    $image = svc()->upload($sekretaris, null, driveUpload('denah.png', $png));
    expect(Storage::disk('local')->get($image->path()))->toBe($png)
        ->and($image->thumbnail_path)->toEndWith('.webp');
    [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($image->thumbnail_path));
    expect(max($w, $h))->toBe(DriveThumbnailer::SIZE);

    $pdf = svc()->upload($sekretaris, null, driveUpload('surat.pdf', drivePdf()));

    if (! DriveThumbnailer::pdfSupported()) {
        expect($pdf->thumbnail_path)->toBeNull();
    }
});

it('menolak jenis berkas terlarang, isi palsu, > 10 MB, dan saat kuota penuh', function (): void {
    $sekretaris = member(RoleName::Sekretaris);

    expect(fn () => svc()->upload($sekretaris, null, driveUpload('virus.pdf', '<?php system($_GET["c"]);')))->toThrow(ValidationException::class);
    expect(fn () => svc()->upload($sekretaris, null, driveUpload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')))->toThrow(ValidationException::class);
    expect(fn () => svc()->upload($sekretaris, null, driveUpload('halaman.html', '<html><body>x</body></html>')))->toThrow(ValidationException::class);
    expect(fn () => svc()->upload($sekretaris, null, driveUpload('besar.pdf', drivePdf(10 * 1024 + 10))))->toThrow(ValidationException::class);

    Setting::set('drive_quota_mb', 1);
    DriveFile::factory()->create(['size' => 1024 * 1024 - 100]);
    expect(fn () => svc()->upload($sekretaris, null, driveUpload('kecil.pdf', drivePdf(5))))->toThrow(ValidationException::class, 'penuh');

    expect(DriveFile::count())->toBe(1);
});

it('folder tidak bisa dipindah ke dirinya sendiri atau ke subfoldernya', function (): void {
    $admin = superAdmin();
    $a = Folder::factory()->create();
    $b = Folder::factory()->in($a)->create();
    $c = Folder::factory()->in($b)->create();

    expect(fn () => svc()->move($admin, $a, $a->id))->toThrow(ValidationException::class);
    expect(fn () => svc()->move($admin, $a, $c->id))->toThrow(ValidationException::class);

    svc()->move($admin, $c, null);
    expect($c->refresh()->parent_id)->toBeNull();

    // Pindah ke folder tanpa akses unggah ditolak.
    $user = member(RoleName::AnggotaKajian);
    FolderPermission::create(['folder_id' => $a->id, 'grantee_type' => 'user', 'grantee_id' => $user->id, 'access' => 'manage']);
    $mine = svc()->createFolder($user, $a->id, 'Punya saya');
    expect(fn () => svc()->move($user, $mine, $c->id))->toThrow(AuthorizationException::class);
});

it('membuang folder ikut membuang isinya (satu batch) dan memulihkannya bersama', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $folder = Folder::factory()->create();
    $sub = Folder::factory()->in($folder)->create();
    $file = DriveFile::factory()->create(['folder_id' => $sub->id]);
    $doc = Document::factory()->create(['folder_id' => $folder->id]);
    $alreadyTrashed = DriveFile::factory()->create(['folder_id' => $folder->id]);
    svc()->trash($sekretaris, $alreadyTrashed);

    svc()->trash($sekretaris, $folder);

    foreach ([$folder, $sub, $file, $doc] as $item) {
        expect($item->fresh()->trashed())->toBeTrue();
    }
    expect(ActivityLog::where('action', 'drive.trashed')->count())->toBe(2);

    svc()->restore($sekretaris, $folder->fresh());

    foreach ([$folder, $sub, $file, $doc] as $item) {
        expect($item->fresh()->trashed())->toBeFalse();
    }
    // Item yang dibuang terpisah sebelumnya tetap di Sampah.
    expect($alreadyTrashed->fresh()->trashed())->toBeTrue();
});

it('item yang dipulihkan sementara foldernya masih di Sampah pindah ke root', function (): void {
    $admin = superAdmin();
    $folder = Folder::factory()->create();
    $file = DriveFile::factory()->create(['folder_id' => $folder->id]);
    svc()->trash($admin, $file);
    svc()->trash($admin, $folder);

    svc()->restore($admin, $file->fresh());

    expect($file->fresh()->trashed())->toBeFalse()->and($file->fresh()->folder_id)->toBeNull();
});

it('aturan pulihkan & hapus permanen: pemilik boleh pulihkan, hanya kelola yang boleh hapus permanen', function (): void {
    $folder = Folder::factory()->create();
    $uploader = member(RoleName::AnggotaMedpub);
    FolderPermission::create(['folder_id' => $folder->id, 'grantee_type' => 'user', 'grantee_id' => $uploader->id, 'access' => 'upload']);

    $file = svc()->upload($uploader, $folder->id, driveUpload('desain.png', drivePng(200, 200)));
    svc()->trash($uploader, $file);

    expect(fn () => svc()->forceDelete($uploader, $file->fresh()))->toThrow(AuthorizationException::class);
    svc()->restore($uploader, $file->fresh());
    svc()->trash($uploader, $file->fresh());

    $path = $file->path();
    svc()->forceDelete(member(RoleName::Sekretaris), $file->fresh());

    expect(DriveFile::withTrashed()->find($file->id))->toBeNull();
    Storage::disk('local')->assertMissing([$path, $file->thumbnail_path]);
    expect(ActivityLog::where('action', 'drive.force_deleted')->exists())->toBeTrue();
});

it('Sampah lebih dari 30 hari dihapus permanen oleh scheduler; yang baru tetap', function (): void {
    $admin = superAdmin();
    $old = Folder::factory()->create();
    $oldFile = DriveFile::factory()->create(['folder_id' => $old->id]);
    $recent = DriveFile::factory()->create();

    $this->travelTo(now()->subDays(31));
    svc()->trash($admin, $old);
    $this->travelBack();
    svc()->trash($admin, $recent);

    artisan('drive:purge-trash')->expectsOutputToContain('2 item')->assertSuccessful();

    expect(Folder::withTrashed()->find($old->id))->toBeNull()
        ->and(DriveFile::withTrashed()->find($oldFile->id))->toBeNull()
        ->and(DriveFile::withTrashed()->find($recent->id))->not->toBeNull();
    Storage::disk('local')->assertMissing($oldFile->path());
    Storage::disk('local')->assertExists($recent->path());
});

it('ZIP folder hanya berisi item yang terlihat, tanpa Sampah, dokumen sebagai HTML', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $folder = Folder::factory()->create(['name' => 'Arsip']);
    $sub = Folder::factory()->in($folder)->create(['name' => 'Sub']);
    DriveFile::factory()->create(['folder_id' => $folder->id, 'original_name' => 'a.pdf']);
    DriveFile::factory()->create(['folder_id' => $sub->id, 'original_name' => 'b.pdf']);
    $gone = DriveFile::factory()->create(['folder_id' => $folder->id, 'original_name' => 'buang.pdf']);
    Document::factory()->create(['folder_id' => $sub->id, 'title' => 'Notulen', 'content' => '<p>Isi notulen</p>']);
    svc()->trash($sekretaris, $gone);

    $path = svc()->zip($sekretaris, $folder);
    $zip = new ZipArchive;
    $zip->open($path);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
    $html = $zip->getFromName('Sub/Notulen.html');
    $zip->close();
    @unlink($path);

    expect($names)->toContain('a.pdf', 'Sub/b.pdf', 'Sub/Notulen.html')->not->toContain('buang.pdf')
        ->and($html)->toContain('Isi notulen');
});

it('bintang disimpan per user dan hanya untuk item yang boleh dilihat', function (): void {
    $folder = Folder::factory()->create();
    $a = member(RoleName::Sekretaris);
    $b = member(RoleName::Pembina);
    $stranger = member(RoleName::AnggotaKajian);

    expect(svc()->toggleStar($a, $folder))->toBeTrue();
    expect(DriveStar::where('user_id', $a->id)->count())->toBe(1)
        ->and(DriveStar::where('user_id', $b->id)->count())->toBe(0);
    expect(svc()->toggleStar($a, $folder))->toBeFalse();
    expect(fn () => svc()->toggleStar($stranger, $folder))->toThrow(AuthorizationException::class);
});

it('nama berkas/folder dibersihkan dan ekstensi berkas dipertahankan saat ganti nama', function (): void {
    $admin = superAdmin();
    $file = DriveFile::factory()->create(['original_name' => 'lama.pdf']);

    svc()->rename($admin, $file, '../../rahasia');
    expect($file->fresh()->original_name)->toBe('.. .. rahasia.pdf');

    expect(fn () => svc()->createFolder($admin, null, '   '))->toThrow(ValidationException::class);
});

it('share mencatat log; berbagi ke divisi memberi akses ke anggotanya', function (): void {
    $folder = Folder::factory()->create();
    svc()->share(member(RoleName::Sekretaris), $folder, DriveGranteeType::Division, divisionId('medpub'), DriveAccess::Upload);

    expect(ActivityLog::where('action', 'drive.shared')->exists())->toBeTrue();
    $file = svc()->upload(member(RoleName::AnggotaMedpub), $folder->id, driveUpload('poster.png', drivePng(100, 100)));
    expect($file->folder_id)->toBe($folder->id);
});
