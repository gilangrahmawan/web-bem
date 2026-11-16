<?php

use App\Enums\RoleName;
use App\Filament\Pages\Backups;
use App\Models\ActivityLog;
use App\Notifications\BackupProblemNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('backups'));

it('halaman Backup & unduhan hanya untuk Super Admin; nama berkas aneh ditolak', function (): void {
    Storage::disk('backups')->put('bem-ubhinus/2026-11-17-01-00-00.zip', 'zip');

    actingAs(superAdmin());
    get(Backups::getUrl())->assertOk()->assertSee('2026-11-17-01-00-00.zip');
    get(route('panel.backups.download', ['file' => '2026-11-17-01-00-00.zip']))->assertOk()->assertDownload('2026-11-17-01-00-00.zip');
    get(route('panel.backups.download', ['file' => '../../.env']))->assertNotFound();
    get(route('panel.backups.download', ['file' => 'tidak-ada.zip']))->assertNotFound();

    foreach ([RoleName::Sekretaris, RoleName::Bendahara, RoleName::Pembina, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get(Backups::getUrl())->assertForbidden();
        get(route('panel.backups.download', ['file' => '2026-11-17-01-00-00.zip']))->assertNotFound();
    }
});

it('backup gagal → lonceng + push ke Super Admin aktif saja, dan dicatat', function (): void {
    Notification::fake();
    $admin = superAdmin();
    $nonaktif = superAdmin(['is_active' => false]);
    $sekretaris = member(RoleName::Sekretaris);

    event(new BackupHasFailed(new RuntimeException('mysqldump tidak ditemukan')));

    Notification::assertSentTo($admin, BackupProblemNotification::class, fn ($n) => str_contains($n->body, 'mysqldump'));
    Notification::assertNotSentTo([$nonaktif, $sekretaris], BackupProblemNotification::class);
    expect(ActivityLog::where('action', 'backup.problem')->exists())->toBeTrue();
});

it('pengingat bulanan dikirim selama backup masih di server; tidak bila sudah memakai R2', function (): void {
    Notification::fake();
    $admin = superAdmin();
    Storage::disk('backups')->put('bem-ubhinus/2026-11-01-01-00-00.zip', str_repeat('x', 2048));

    $this->artisan('bem:backup-reminder')->assertSuccessful();
    Notification::assertSentTo($admin, BackupProblemNotification::class, fn ($n) => str_contains($n->title, 'Pengingat bulanan'));

    Notification::fake();
    config(['backup.backup.destination.disks' => ['r2']]);
    $this->artisan('bem:backup-reminder')->expectsOutputToContain('penyimpanan luar')->assertSuccessful();
    Notification::assertNothingSent();
});

it('HSTS tidak dikirim secara default; hanya bila HSTS_ENABLED dan request HTTPS', function (): void {
    get('/')->assertHeaderMissing('Strict-Transport-Security');

    config(['bem.hsts.enabled' => true]);
    get('/')->assertHeaderMissing('Strict-Transport-Security'); // HTTP biasa
    get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('konfigurasi produksi: backup terenkripsi ke disk terpisah, session terenkripsi', function (): void {
    expect(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('backup.backup.encryption'))->toBe('default')
        ->and(config('backup.backup.source.files.include'))->toContain(storage_path('app/private'), storage_path('app/public'))
        ->and(config('filesystems.disks.backups.root'))->toBe(storage_path('backups'))
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.lifetime'))->toBe(30);
});
