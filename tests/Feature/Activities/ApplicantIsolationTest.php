<?php

use App\Enums\AnnouncementType;
use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Filament\Pages\Calendar;
use App\Filament\Pages\Inbox;
use App\Filament\Resources\StructureMembers\Pages\CreateStructureMember;
use App\Filament\Widgets\ActivityQueueWidget;
use App\Filament\Widgets\ActivityStatusChart;
use App\Filament\Widgets\AspirationStatusChart;
use App\Filament\Widgets\ContentQueueWidget;
use App\Filament\Widgets\DiscussionStatusChart;
use App\Filament\Widgets\InboxStatsWidget;
use App\Filament\Widgets\KasStatsOverview;
use App\Filament\Widgets\LpjDeadlineWidget;
use App\Filament\Widgets\MessageReadRateChart;
use App\Filament\Widgets\MyActivitiesChart;
use App\Filament\Widgets\MyKasWidget;
use App\Filament\Widgets\MyPostsChart;
use App\Filament\Widgets\OverdueLpjWidget;
use App\Filament\Widgets\PostsPublishedChart;
use App\Filament\Widgets\PostStatusChart;
use App\Models\AnnouncementRecipient;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\KasService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

function applicant(array $attributes = []): User
{
    return member(RoleName::PengajuKegiatan, $attributes);
}

it('JARING PENGAMAN: pengaju hanya bisa membuka halaman yang diizinkan; semua halaman/resource lain ditolak', function (): void {
    $allowed = [
        'filament.admin.pages.dashboard',
        'filament.admin.pages.profil-saya',
        'filament.admin.pages.ganti-password',
        'filament.admin.pages.kalender',
        'filament.admin.pages.kotak-masuk',
        // Fase 9: pengaju boleh chat (hanya percakapan miliknya) & mengatur notifikasinya.
        'filament.admin.pages.chat',
        'filament.admin.pages.notifikasi',
        'filament.admin.resources.kegiatan.index',
        'filament.admin.resources.kegiatan.create',
    ];

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'filament.admin.')
            && in_array('GET', $r->methods(), true)
            && ! str_contains($r->uri(), '{')
            && ! str_contains((string) $r->getName(), '.auth.'))
        ->mapWithKeys(fn ($r) => [$r->getName() => '/'.$r->uri()]);

    expect($routes->count())->toBeGreaterThan(25);

    $user = applicant();

    foreach ($routes as $name => $uri) {
        $status = actingAs($user)->get($uri)->getStatusCode();

        in_array($name, $allowed, true)
            ? expect($status)->toBe(200, "{$name} ({$uri}) seharusnya boleh dibuka pengaju")
            : expect($status)->toBeIn([403, 404], "{$name} ({$uri}) seharusnya DITOLAK untuk pengaju, dapat {$status}");
    }
});

it('pengaju tanpa organisasi aktif tidak bisa masuk panel', function (): void {
    $tanpaOrganisasi = userWithRole(RoleName::PengajuKegiatan);
    $orgNonaktif = applicant(['organization_id' => Organization::factory()->create(['is_active' => false])->id]);

    $panel = Filament::getPanel('admin');
    expect($tanpaOrganisasi->canAccessPanel($panel))->toBeFalse()
        ->and($orgNonaktif->canAccessPanel($panel))->toBeFalse()
        ->and(applicant()->canAccessPanel($panel))->toBeTrue();
});

it('pengaju tidak wajib kas', function (): void {
    $pengaju = applicant();

    expect(app(KasService::class)->payersQuery()->pluck('id'))->not->toContain($pengaju->id)
        ->and(app(KasService::class)->isPayer($pengaju))->toBeFalse();
});

it('pengaju tidak pernah masuk target pengurus: semua, per role, per divisi, maupun user tertentu', function (): void {
    $pengaju = applicant();
    $pengurus = member(RoleName::AnggotaKajian);
    $service = app(AnnouncementService::class);
    $sekretaris = member(RoleName::Sekretaris);

    $resolved = collect([
        $service->resolveRecipients($sekretaris, true, [], [], []),
        $service->resolveRecipients($sekretaris, false, ['pengaju_kegiatan', 'anggota_kajian'], [], []),
        $service->resolveRecipients($sekretaris, false, [], [divisionId('kajian')], []),
        $service->resolveRecipients($sekretaris, false, [], [], [$pengaju->id, $pengurus->id]),
    ]);

    foreach ($resolved as $recipients) {
        expect($recipients->pluck('id'))->not->toContain($pengaju->id);
    }

    expect($resolved[0]->pluck('id'))->toContain($pengurus->id);
});

it('target organisasi (semua / tertentu) sampai ke akun pengaju aktif organisasi itu saja', function (): void {
    $futsal = Organization::factory()->create(['name' => 'UKM Futsal Uji']);
    $musik = Organization::factory()->create(['name' => 'UKM Musik Uji']);
    $nonaktif = Organization::factory()->create(['is_active' => false]);
    $a = applicant(['organization_id' => $futsal->id]);
    $b = applicant(['organization_id' => $musik->id]);
    $c = applicant(['organization_id' => $futsal->id, 'is_active' => false]);
    $d = applicant(['organization_id' => $nonaktif->id]);
    $pengurus = member(RoleName::AnggotaHumas);
    $service = app(AnnouncementService::class);

    $tertentu = $service->send(member(RoleName::KoordinatorHumas), AnnouncementType::Letter, [
        'title' => 'Undangan rapat UKM', 'body' => 'Hadir ya.', 'target_all' => false, 'organizations' => [$futsal->id],
    ])->recipients()->pluck('user_id')->all();

    expect($tertentu)->toBe([$a->id]);

    $semua = $service->send(member(RoleName::Sekretaris), AnnouncementType::Announcement, [
        'title' => 'Info LPJ', 'body' => 'Tenggat 14 hari.', 'target_all' => false, 'target_all_organizations' => true,
    ])->recipients()->pluck('user_id')->sort()->values()->all();

    expect($semua)->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and($semua)->not->toContain($c->id)->not->toContain($d->id)->not->toContain($pengurus->id);
});

it('pengaju membuka Kotak Masuk yang hanya berisi pesan untuknya', function (): void {
    $org = Organization::factory()->create();
    $saya = applicant(['organization_id' => $org->id]);
    app(AnnouncementService::class)->send(member(RoleName::Sekretaris), AnnouncementType::Announcement, [
        'title' => 'Untuk organisasi', 'body' => 'x', 'target_all' => false, 'organizations' => [$org->id],
    ]);
    app(AnnouncementService::class)->send(member(RoleName::Sekretaris), AnnouncementType::Announcement, [
        'title' => 'Untuk pengurus', 'body' => 'x', 'target_all' => true,
    ]);

    actingAs($saya);
    livewire(Inbox::class)
        ->assertCanSeeTableRecords(AnnouncementRecipient::where('user_id', $saya->id)->get())
        ->assertCanNotSeeTableRecords(AnnouncementRecipient::where('user_id', '!=', $saya->id)->get());

    expect(AnnouncementRecipient::where('user_id', $saya->id)->count())->toBe(1)
        ->and(InboxStatsWidget::canView())->toBeTrue();
});

it('pengaju hanya melihat kalender publik', function (): void {
    $date = now()->startOfMonth()->addDays(10)->toDateString();
    Event::factory()->category(EventCategory::Meeting)->on($date)->create(['title' => 'Rapat Umum Pengurus', 'division_id' => null]);
    Event::factory()->category(EventCategory::Internal)->on($date)->create(['title' => 'Internal Rahasia']);
    Event::factory()->category(EventCategory::Ukm)->on($date)->create(['title' => 'Turnamen Publik']);
    $pengaju = applicant();

    expect(Event::query()->visibleTo($pengaju)->pluck('title')->all())->toBe(['Turnamen Publik']);

    actingAs($pengaju);
    $titles = collect(livewire(Calendar::class)->instance()->fetchEvents(
        now()->startOfMonth()->toIso8601String(), now()->startOfMonth()->addDays(40)->toIso8601String(),
        array_map(fn (EventCategory $c) => $c->value, EventCategory::cases()),
    ))->pluck('title');

    expect($titles)->toContain('Turnamen Publik')->not->toContain('Rapat Umum Pengurus')->not->toContain('Internal Rahasia')
        ->and(livewire(Calendar::class)->instance()->getAction('createEvent')?->isVisible())->toBeFalse();
});

it('pengaju tidak bisa ditautkan ke struktur atau dipilih sebagai penanggung jawab tugas', function (): void {
    $pengaju = applicant(['name' => 'Pengaju Tidak Boleh Masuk']);
    actingAs(superAdmin());

    $structureOptions = livewire(CreateStructureMember::class)
        ->instance()->form->getComponent('user_id')->getOptions();

    expect($structureOptions)->not->toContain('Pengaju Tidak Boleh Masuk');
});

it('widget internal tidak tampil untuk pengaju; widget kegiatan pengaju tampil', function (): void {
    actingAs(applicant());

    foreach ([
        MyKasWidget::class, KasStatsOverview::class,
        AspirationStatusChart::class, PostStatusChart::class,
        PostsPublishedChart::class, MyPostsChart::class,
        DiscussionStatusChart::class, MessageReadRateChart::class,
        ContentQueueWidget::class, ActivityStatusChart::class,
        ActivityQueueWidget::class, OverdueLpjWidget::class,
    ] as $widget) {
        expect($widget::canView())->toBeFalse($widget);
    }

    expect(MyActivitiesChart::canView())->toBeTrue()->and(LpjDeadlineWidget::canView())->toBeTrue();
});
