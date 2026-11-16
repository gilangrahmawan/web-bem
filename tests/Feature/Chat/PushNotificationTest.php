<?php

use App\Enums\DriveAccess;
use App\Enums\DriveGranteeType;
use App\Enums\NotificationCategory;
use App\Enums\RoleName;
use App\Filament\Pages\NotificationSettings;
use App\Models\EventAssignee;
use App\Models\Folder;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Models\User;
use App\Notifications\DriveFolderSharedNotification;
use App\Notifications\KasPaidNotification;
use App\Notifications\RecruitmentApplicantRegisteredNotification;
use App\Notifications\TaskReminderNotification;
use App\Notifications\TestPushNotification;
use App\Services\Drive\DriveService;
use App\Services\RecruitmentService;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    config(['webpush.vapid.public_key' => 'kunci-publik-uji', 'webpush.vapid.private_key' => 'kunci-privat-uji']);
});

function withDevice(User $user, string $suffix = 'a'): User
{
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/'.$user->id.$suffix, 'p256dh-uji', 'auth-uji');

    return $user;
}

it('semua notifikasi lama mendapat channel webpush bila user punya perangkat; lonceng tetap langsung (sync)', function (): void {
    $user = withDevice(member(RoleName::AnggotaKajian));
    $tanpaPerangkat = member(RoleName::AnggotaHumas);
    $notification = new KasPaidNotification([1, 2], 10000);

    expect($notification->via($user))->toBe(['database', WebPushChannel::class])
        ->and($notification->via($tanpaPerangkat))->toBe(['database'])
        ->and($notification->viaConnections())->toMatchArray(['database' => 'sync', WebPushChannel::class => config('queue.default')])
        ->and($notification)->toBeInstanceOf(ShouldQueue::class);

    // Tiap kelas notifikasi punya kategori push.
    foreach (glob(app_path('Notifications/*.php')) as $file) {
        $class = 'App\\Notifications\\'.basename($file, '.php');
        expect(method_exists($class, 'pushCategory'))->toBeTrue("{$class} belum mendukung push");
    }
});

it('preferensi mematikan push per jenis, lonceng tetap masuk; user nonaktif tidak dikirimi push', function (): void {
    $user = withDevice(member(RoleName::AnggotaMedpub));
    $user->forceFill(['notification_preferences' => [NotificationCategory::Kas->value => false]])->save();

    expect((new KasPaidNotification([1], 5000))->via($user))->toBe(['database'])
        ->and((new TaskReminderNotification(...taskReminderArgs($user)))->via($user))->toContain(WebPushChannel::class);

    $user->forceFill(['is_active' => false, 'notification_preferences' => null])->save();
    expect((new KasPaidNotification([1], 5000))->via($user))->toBe(['database']);
});

function taskReminderArgs(User $user): array
{
    $constructor = new ReflectionMethod(TaskReminderNotification::class, '__construct');
    $assignee = EventAssignee::factory()->create(['user_id' => $user->id]);

    return count($constructor->getParameters()) === 1 ? [$assignee] : [$assignee, 1];
}

it('isi push diambil dari pesan lonceng (judul, isi, URL) dan dikirim lewat WebPush tanpa server sungguhan', function (): void {
    $user = withDevice(member(RoleName::Bendahara, ['name' => 'Rani']));

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once()->withArgs(function ($subscription, $payload) use (&$sent): bool {
        $sent = json_decode($payload, true);

        return true;
    });
    $webPush->shouldReceive('flush')->once()->andReturn((function () {
        yield from [];
    })());
    app()->instance(WebPushChannel::class, new WebPushChannel($webPush, app(ReportHandler::class)));

    $user->notifyNow(new KasPaidNotification([3], 5000), [WebPushChannel::class]);

    expect($sent['title'])->toBe('Pembayaran kas diterima')
        ->and($sent['body'])->toContain('Rani')
        ->and($sent['icon'])->toBe('/images/icon-192.png')
        ->and($sent['data']['url'])->toBeString();
});

it('langganan kedaluwarsa (410) dihapus otomatis', function (): void {
    $user = withDevice(member(RoleName::Sekretaris));
    $endpoint = $user->pushSubscriptions()->value('endpoint');

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once();
    $webPush->shouldReceive('flush')->once()->andReturn((function () use ($endpoint) {
        yield new MessageSentReport(new PsrRequest('POST', $endpoint), new PsrResponse(410), false, 'Gone');
    })());
    app()->instance(WebPushChannel::class, new WebPushChannel($webPush, app(ReportHandler::class)));

    $user->notifyNow(new TestPushNotification);

    expect(PushSubscription::count())->toBe(0);
});

it('Oprec: pendaftar baru → Sekretaris & koordinator divisi pilihan saja', function (): void {
    Notification::fake();
    $sekretaris = member(RoleName::Sekretaris);
    $koorKajian = member(RoleName::KoordinatorKajian);
    $koorMedpub = member(RoleName::KoordinatorMedpub);
    $koorHumas = member(RoleName::KoordinatorHumas);
    $wave = Recruitment::factory()->create();

    $applicant = RecruitmentApplicant::factory()->create([
        'recruitment_id' => $wave->id, 'first_choice_division_id' => divisionId('kajian'), 'second_choice_division_id' => divisionId('humas'),
    ]);
    (new ReflectionMethod(RecruitmentService::class, 'notifyNewApplicant'))->invoke(app(RecruitmentService::class), $applicant);

    Notification::assertSentTo([$sekretaris, $koorKajian, $koorHumas], RecruitmentApplicantRegisteredNotification::class);
    Notification::assertNotSentTo($koorMedpub, RecruitmentApplicantRegisteredNotification::class);
});

it('Drive: "folder dibagikan kepada Anda" ke anggota divisi penerima, bukan ke pembagi', function (): void {
    Notification::fake();
    $sekretaris = member(RoleName::Sekretaris);
    $medpub1 = member(RoleName::AnggotaMedpub);
    $medpub2 = member(RoleName::KoordinatorMedpub);
    $kajian = member(RoleName::AnggotaKajian);
    $folder = Folder::factory()->create();

    app(DriveService::class)->share($sekretaris, $folder, DriveGranteeType::Division, divisionId('medpub'), DriveAccess::Upload);

    Notification::assertSentTo([$medpub1, $medpub2], DriveFolderSharedNotification::class);
    Notification::assertNotSentTo([$sekretaris, $kajian], DriveFolderSharedNotification::class);

    // Bagikan ulang dengan akses sama → tidak ada notifikasi ganda.
    Notification::fake();
    app(DriveService::class)->share($sekretaris, $folder, DriveGranteeType::Division, divisionId('medpub'), DriveAccess::Upload);
    Notification::assertNothingSent();
});

it('langganan push hanya untuk user yang login, endpoint wajib HTTPS, hanya miliknya', function (): void {
    $user = member(RoleName::AnggotaKajian);
    $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'k', 'auth' => 'a'], 'contentEncoding' => 'aes128gcm'];

    postJson(route('panel.push.subscribe'), $payload)->assertUnauthorized();

    actingAs($user);
    postJson(route('panel.push.subscribe'), ['endpoint' => 'http://jahat.test/x', 'keys' => ['p256dh' => 'k', 'auth' => 'a']])->assertUnprocessable();
    postJson(route('panel.push.subscribe'), $payload)->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(1);

    deleteJson(route('panel.push.unsubscribe'), ['endpoint' => $payload['endpoint']])->assertOk();
    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('halaman Notifikasi: preferensi tersimpan, hapus perangkat sendiri, kirim uji; terbuka untuk pengaju & Pembina', function (): void {
    Notification::fake();
    $user = withDevice(member(RoleName::AnggotaHumas));
    $other = withDevice(member(RoleName::AnggotaKajian), 'b');
    $othersDevice = $other->pushSubscriptions()->value('id');

    actingAs($user);
    $page = livewire(NotificationSettings::class)
        ->set('preferences.chat', false)
        ->assertSee('Panduan iPhone')->assertSee('Chrome / Android');
    expect($user->fresh()->wantsPush(NotificationCategory::Chat))->toBeFalse()
        ->and($user->fresh()->wantsPush(NotificationCategory::Tasks))->toBeTrue();

    $page->call('sendTest');
    Notification::assertSentTo($user, TestPushNotification::class);

    $page->call('removeDevice', $othersDevice);
    expect(PushSubscription::find($othersDevice))->not->toBeNull();
    $page->call('removeDevice', $user->pushSubscriptions()->value('id'));
    expect($user->pushSubscriptions()->count())->toBe(0);

    foreach ([RoleName::PengajuKegiatan, RoleName::Pembina] as $role) {
        actingAs(member($role));
        get(NotificationSettings::getUrl())->assertOk();
    }
});

it('panel memuat manifest PWA & service worker; halaman offline tersedia', function (): void {
    actingAs(member(RoleName::Sekretaris));

    get('/panel-bem')->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('apple-touch-icon', false)
        ->assertSee('BEM_REALTIME', false);

    expect(json_decode(file_get_contents(public_path('manifest.webmanifest')), true))
        ->toMatchArray(['start_url' => '/panel-bem', 'display' => 'standalone'])
        ->and(file_get_contents(public_path('sw.js')))->toContain("addEventListener('push'")
        ->and(is_file(public_path('offline.html')))->toBeTrue();
});
