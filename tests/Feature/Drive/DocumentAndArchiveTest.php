<?php

use App\Enums\AnnouncementType;
use App\Enums\DriveAccess;
use App\Enums\RoleName;
use App\Filament\Pages\DocumentEditor;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DriveFile;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\Setting;
use App\Notifications\DriveArchiveFailedNotification;
use App\Services\AnnouncementService;
use App\Services\Drive\DocumentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('local'));

function docs(): DocumentService
{
    return app(DocumentService::class);
}

function sharedFolderFor(array $users, DriveAccess $access = DriveAccess::Upload): Folder
{
    $folder = Folder::factory()->create();

    foreach ($users as $user) {
        FolderPermission::create(['folder_id' => $folder->id, 'grantee_type' => 'user', 'grantee_id' => $user->id, 'access' => $access->value]);
    }

    return $folder;
}

it('dokumen dari template membawa isi & kop; template bawaan tersedia', function (): void {
    expect(DocumentTemplate::pluck('name')->all())
        ->toContain('Surat Undangan', 'Surat Peminjaman', 'Proposal Kegiatan', 'Laporan Pertanggungjawaban (LPJ)', 'Notulen Rapat');

    $template = DocumentTemplate::where('name', 'Notulen Rapat')->sole();
    $doc = docs()->create(member(RoleName::Sekretaris), null, 'Notulen Pleno 1', $template->id);

    expect($doc->has_letterhead)->toBeTrue()->and($doc->content)->toContain('NOTULEN RAPAT');
});

it('isi dokumen disanitasi saat disimpan (script, event handler, javascript: dibuang; perataan teks tetap)', function (): void {
    $user = member(RoleName::Sekretaris);
    $doc = docs()->create($user, null, 'Uji XSS');

    docs()->save($user, $doc, '<p style="text-align: center; color: red" onclick="x()">Halo</p><script>alert(1)</script>'
        .'<img src=x onerror=alert(1)><a href="javascript:alert(1)">klik</a><iframe src="https://jahat.test"></iframe>');

    $html = $doc->fresh()->content;
    expect($html)->toContain('text-align:center')->toContain('Halo')
        ->not->toContain('<script')->not->toContain('onerror')->not->toContain('onclick')
        ->not->toContain('javascript:')->not->toContain('<iframe')->not->toContain('<img')->not->toContain('color');
});

it('dokumen terkunci 15 menit: user lain tidak bisa menyimpan; setelah kedaluwarsa bisa', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::KoordinatorKajian);
    $folder = sharedFolderFor([$a, $b]);
    $doc = docs()->create($a, $folder->id, 'Kajian UKT');

    expect(docs()->acquireLock($a, $doc))->toBeTrue()
        ->and(docs()->acquireLock($b, $doc->fresh()))->toBeFalse();
    expect(fn () => docs()->save($b, $doc->fresh(), '<p>Timpa</p>'))->toThrow(ValidationException::class, 'sedang diedit');

    docs()->save($a, $doc->fresh(), '<p>Versi A</p>');
    expect($doc->fresh()->content)->toBe('<p>Versi A</p>');

    $this->travel(16)->minutes();
    expect(docs()->acquireLock($b, $doc->fresh()))->toBeTrue();
    docs()->save($b, $doc->fresh(), '<p>Versi B</p>');
    expect($doc->fresh()->content)->toBe('<p>Versi B</p>');

    docs()->releaseLock($b, $doc);
    expect($doc->fresh()->locked_by)->toBeNull();
});

it('versi dibuat maksimal 1 per menit per user dan bisa dipulihkan', function (): void {
    $user = member(RoleName::Sekretaris);
    $doc = docs()->create($user, null, 'Rencana');

    docs()->save($user, $doc, '<p>v1</p>');
    docs()->save($user, $doc->fresh(), '<p>v1 + koreksi</p>');
    expect($doc->versions()->count())->toBe(1)->and($doc->versions()->first()->content)->toBe('<p>v1 + koreksi</p>');

    $this->travel(61)->seconds();
    docs()->save($user, $doc->fresh(), '<p>v2</p>');
    expect($doc->versions()->count())->toBe(2);

    // Simpan tanpa perubahan tidak menambah versi.
    $this->travel(61)->seconds();
    docs()->save($user, $doc->fresh(), '<p>v2</p>');
    expect($doc->versions()->count())->toBe(2);

    $first = $doc->versions()->reorder()->oldest('id')->first();
    docs()->restoreVersion($user, $doc->fresh(), $first);
    expect($doc->fresh()->content)->toBe('<p>v1 + koreksi</p>');
});

it('akses lihat saja tidak bisa mengedit dokumen; pengurus tanpa akses tidak bisa membuka', function (): void {
    $editor = member(RoleName::AnggotaHumas);
    $viewer = member(RoleName::AnggotaKajian);
    $folder = sharedFolderFor([$editor]);
    FolderPermission::create(['folder_id' => $folder->id, 'grantee_type' => 'user', 'grantee_id' => $viewer->id, 'access' => 'view']);
    $doc = docs()->create($editor, $folder->id, 'Draft surat');

    expect(fn () => docs()->save($viewer, $doc, '<p>Ubah</p>'))->toThrow(AuthorizationException::class);
    expect(docs()->acquireLock($viewer, $doc))->toBeFalse();

    actingAs($viewer);
    get(DocumentEditor::getUrl(['document' => $doc->id]))->assertOk()->assertSee('Mode baca');

    actingAs(member(RoleName::Bendahara));
    get(DocumentEditor::getUrl(['document' => $doc->id]))->assertNotFound();
});

it('editor: pemegang kunci mengedit & auto-save; pengguna kedua melihat status terkunci', function (): void {
    $a = member(RoleName::AnggotaKajian);
    $b = member(RoleName::KoordinatorKajian);
    $folder = sharedFolderFor([$a, $b]);
    $doc = docs()->create($a, $folder->id, 'Kajian Parkir');

    actingAs($a);
    livewire(DocumentEditor::class, ['document' => $doc->id])
        ->assertSet('editing', true)
        ->set('data.content', '<p>Ditulis A</p>')
        ->call('autosave');
    expect($doc->fresh()->content)->toBe('<p>Ditulis A</p>');

    actingAs($b);
    livewire(DocumentEditor::class, ['document' => $doc->id])
        ->assertSet('editing', false)
        ->assertSee('Sedang diedit oleh')
        ->assertSee($a->name)
        ->call('autosave');
    expect($doc->fresh()->content)->toBe('<p>Ditulis A</p>');
});

it('export PDF & DOCX (dengan kop bila dari template) untuk yang berhak', function (): void {
    $user = member(RoleName::Sekretaris);
    $template = DocumentTemplate::where('name', 'Surat Undangan')->sole();
    Setting::set('cabinet_name', 'Kabinet Harmoni');
    $doc = docs()->create($user, null, 'Undangan Rapat', $template->id);

    actingAs($user);
    $pdf = get(route('panel.drive.documents.export', [$doc, 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(file_get_contents($pdf->baseResponse->getFile()->getPathname()))->toStartWith('%PDF');

    $docx = get(route('panel.drive.documents.export', [$doc, 'docx']))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $zip = new ZipArchive;
    $zip->open($docx->baseResponse->getFile()->getPathname());
    expect($zip->getFromName('word/document.xml'))->toContain('BADAN EKSEKUTIF MAHASISWA')->toContain('Kabinet Harmoni')->toContain('Undangan Rapat');
    $zip->close();
});

it('surat Humas berlampiran diarsipkan ke Drive "Surat/{tahun}": lampiran disalin + dokumen ringkasan', function (): void {
    $this->travelTo('2026-11-20 10:00:00');
    Storage::disk('local')->put('announcements/undangan.pdf', '%PDF-1.4 lampiran asli');
    $penerima = member(RoleName::KoordinatorKajian, ['name' => 'Rani Penerima']);
    member(RoleName::Sekretaris);

    $letter = app(AnnouncementService::class)->send(member(RoleName::KoordinatorHumas, ['name' => 'Bima Humas']), AnnouncementType::Letter, [
        'title' => 'Undangan Audiensi', 'body' => "Mohon hadir.\nTerima kasih.", 'target_all' => false, 'users' => [$penerima->id],
        'attachment_path' => 'announcements/undangan.pdf', 'attachment_name' => 'Undangan Audiensi.pdf',
    ]);

    $year = Folder::where('system_key', 'letters:2026')->sole();
    expect($year->is_system)->toBeTrue()->and($year->parent->system_key)->toBe('letters');

    $copy = DriveFile::where('folder_id', $year->id)->sole();
    expect($copy->original_name)->toBe('Undangan Audiensi.pdf')
        ->and(Storage::disk('local')->get($copy->path()))->toBe('%PDF-1.4 lampiran asli');
    Storage::disk('local')->assertExists('announcements/undangan.pdf');

    $summary = Document::where('folder_id', $year->id)->sole();
    expect($summary->title)->toBe('Surat: Undangan Audiensi')
        ->and($summary->content)->toContain('Bima Humas')->toContain('Rani Penerima')->toContain('Mohon hadir.')
        ->and(ActivityLog::where('action', 'drive.letter_archived')->where('subject_id', $letter->id)->exists())->toBeTrue();

    // Humas bisa melihat arsip; divisi lain tidak.
    actingAs(member(RoleName::AnggotaHumas));
    get(route('panel.drive.files.download', $copy))->assertOk();
    actingAs(member(RoleName::AnggotaMedpub));
    get(route('panel.drive.files.download', $copy))->assertNotFound();
});

it('surat tanpa lampiran dan pengumuman tidak diarsipkan', function (): void {
    member(RoleName::Bendahara);
    $service = app(AnnouncementService::class);

    $service->send(member(RoleName::KoordinatorHumas), AnnouncementType::Letter, ['title' => 'Info', 'body' => 'Isi', 'target_all' => true]);
    Storage::disk('local')->put('announcements/a.pdf', '%PDF');
    $service->send(member(RoleName::Sekretaris), AnnouncementType::Announcement, [
        'title' => 'Pengumuman', 'body' => 'Isi', 'target_all' => true, 'attachment_path' => 'announcements/a.pdf', 'attachment_name' => 'a.pdf',
    ]);

    expect(DriveFile::count())->toBe(0)->and(Document::count())->toBe(0);
});

it('arsip gagal (kuota penuh) tidak menggagalkan surat; dicatat & Sekretaris diberi notifikasi', function (): void {
    Notification::fake();
    Setting::set('drive_quota_mb', 1);
    DriveFile::factory()->create(['size' => 1024 * 1024]);
    Storage::disk('local')->put('announcements/besar.pdf', '%PDF-1.4 isi');
    $sekretaris = member(RoleName::Sekretaris);
    member(RoleName::Bendahara);

    $letter = app(AnnouncementService::class)->send(member(RoleName::KoordinatorHumas), AnnouncementType::Letter, [
        'title' => 'Surat Penting', 'body' => 'Isi', 'target_all' => true,
        'attachment_path' => 'announcements/besar.pdf', 'attachment_name' => 'besar.pdf',
    ]);

    expect(Announcement::find($letter->id))->not->toBeNull()
        ->and($letter->recipients()->count())->toBeGreaterThan(0)
        ->and(Document::count())->toBe(0)
        ->and(DriveFile::count())->toBe(1)
        ->and(ActivityLog::where('action', 'drive.letter_archive_failed')->exists())->toBeTrue();
    Notification::assertSentTo($sekretaris, DriveArchiveFailedNotification::class);
});
