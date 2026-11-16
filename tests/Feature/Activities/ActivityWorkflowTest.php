<?php

use App\Enums\ActivityStatus;
use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Filament\Resources\StudentActivities\Pages\ViewStudentActivity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\StudentActivity;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\Activities\LpjSanctionPolicy;
use App\Services\ActivityWorkflowService;
use App\Services\EventService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Storage::fake('local');
});

function activities(): ActivityWorkflowService
{
    return app(ActivityWorkflowService::class);
}

/** Tim review lengkap: Sekretaris, Bendahara, Ketua, Wakil. */
function reviewTeam(): array
{
    return [
        'sekretaris' => member(RoleName::Sekretaris),
        'bendahara' => member(RoleName::Bendahara),
        'ketua' => superAdmin(['name' => 'Ketua']),
        'wakil' => superAdmin(['name' => 'Wakil']),
    ];
}

function draftActivity(User $pic, array $overrides = []): StudentActivity
{
    Storage::disk('local')->put('activities/proposal.pdf', '%PDF-1.4');

    return activities()->saveDraft($pic, [
        'name' => 'Turnamen Futsal Antar-Prodi',
        'starts_on' => now()->addDays(20)->toDateString(),
        'ends_on' => now()->addDays(21)->toDateString(),
        'location' => 'GOR Kampus',
        'budget_estimate' => 3500000,
        'proposal_path' => 'activities/proposal.pdf',
        'proposal_name' => 'Proposal Turnamen.pdf',
        ...$overrides,
    ]);
}

function approvedActivity(User $pic, array $team): StudentActivity
{
    $activity = draftActivity($pic);
    activities()->submitProposal($pic, $activity);
    activities()->review($team['sekretaris'], $activity->fresh(), 'approve');
    activities()->review($team['bendahara'], $activity->fresh(), 'approve');
    activities()->review($team['ketua'], $activity->fresh(), 'approve');

    return $activity->fresh();
}

it('alur lengkap: proposal → Sekretaris → Bendahara → Ketua → kalender → LPJ → selesai', function (): void {
    Notification::fake();
    $team = reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $activity = draftActivity($pengaju);

    expect($activity->pic_user_id)->toBe($pengaju->id)
        ->and($activity->organization_id)->toBe($pengaju->organization_id)
        ->and($activity->status)->toBe(ActivityStatus::Draft);

    activities()->submitProposal($pengaju, $activity);
    Notification::assertSentTo($team['sekretaris'], ActivityNotification::class);

    activities()->review($team['sekretaris'], $activity->fresh(), 'approve');
    expect($activity->fresh()->status)->toBe(ActivityStatus::ProposalVerified);
    Notification::assertSentTo($team['bendahara'], ActivityNotification::class);

    activities()->review($team['bendahara'], $activity->fresh(), 'approve');
    expect($activity->fresh()->status)->toBe(ActivityStatus::ProposalBudgetReviewed);

    activities()->review($team['ketua'], $activity->fresh(), 'approve');
    $activity->refresh();

    expect($activity->status)->toBe(ActivityStatus::Approved)
        ->and($activity->event)->not->toBeNull()
        ->and($activity->event->category)->toBe(EventCategory::Ukm)
        ->and($activity->event->organizer)->toBe($activity->organization->name);
    Notification::assertSentTo($pengaju, ActivityNotification::class, fn ($n) => $n->title === 'Proposal disetujui');

    Storage::disk('local')->put('activities/lpj.pdf', '%PDF-1.4');
    activities()->submitLpj($pengaju, $activity, 'activities/lpj.pdf', 'LPJ Turnamen.pdf', 3200000);
    activities()->review($team['sekretaris'], $activity->fresh(), 'approve');
    activities()->review($team['bendahara'], $activity->fresh(), 'approve');
    activities()->review($team['wakil'], $activity->fresh(), 'approve');

    $activity->refresh();
    expect($activity->status)->toBe(ActivityStatus::Completed)
        ->and($activity->budget_realization)->toBe(3200000)
        ->and($activity->completed_at)->not->toBeNull()
        ->and($activity->logs()->count())->toBeGreaterThanOrEqual(9);
    $this->assertDatabaseHas('activity_logs', ['action' => 'activity.status_changed', 'subject_id' => $activity->id]);
});

it('revisi bisa diminta di setiap tahap dan pengajuan ulang mulai lagi dari Sekretaris', function (string $step): void {
    $team = reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $activity = draftActivity($pengaju);
    activities()->submitProposal($pengaju, $activity);

    $reviewers = ['secretary' => [], 'treasurer' => ['sekretaris'], 'leader' => ['sekretaris', 'bendahara']][$step];
    foreach ($reviewers as $who) {
        activities()->review($team[$who], $activity->fresh(), 'approve');
    }

    $by = ['secretary' => 'sekretaris', 'treasurer' => 'bendahara', 'leader' => 'ketua'][$step];
    expect(fn () => activities()->review($team[$by], $activity->fresh(), 'revision', ''))->toThrow(ValidationException::class);

    activities()->review($team[$by], $activity->fresh(), 'revision', 'Lengkapi rincian anggaran konsumsi.');
    expect($activity->fresh()->status)->toBe(ActivityStatus::ProposalRevision)
        ->and(Gate::forUser($pengaju)->allows('update', $activity->fresh()))->toBeTrue();

    activities()->submitProposal($pengaju, $activity->fresh());
    expect($activity->fresh()->status)->toBe(ActivityStatus::ProposalSubmitted);
})->with(['secretary', 'treasurer', 'leader']);

it('menolak proposal hanya Ketua/Wakil; LPJ tidak bisa ditolak', function (): void {
    $team = reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $activity = draftActivity($pengaju);
    activities()->submitProposal($pengaju, $activity);

    expect(fn () => activities()->review($team['sekretaris'], $activity->fresh(), 'reject', 'Tidak sesuai.'))->toThrow(ValidationException::class);

    $approved = approvedActivity(member(RoleName::PengajuKegiatan), $team);
    Storage::disk('local')->put('activities/lpj2.pdf', '%PDF');
    activities()->submitLpj($approved->pic, $approved, 'activities/lpj2.pdf', 'LPJ.pdf', 100);
    activities()->review($team['sekretaris'], $approved->fresh(), 'approve');
    activities()->review($team['bendahara'], $approved->fresh(), 'approve');

    expect(fn () => activities()->review($team['ketua'], $approved->fresh(), 'reject', 'Ditolak saja.'))
        ->toThrow(ValidationException::class, 'LPJ tidak bisa ditolak');

    activities()->review($team['sekretaris'], $activity->fresh(), 'approve');
    activities()->review($team['bendahara'], $activity->fresh(), 'approve');
    activities()->review($team['ketua'], $activity->fresh(), 'reject', 'Kegiatan bertabrakan dengan UAS.');

    expect($activity->fresh()->status)->toBe(ActivityStatus::Rejected)->and($activity->fresh()->event_id)->toBeNull();
});

it('LPJ hanya bisa diunggah setelah proposal disetujui, oleh penanggung jawab', function (): void {
    $team = reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $draft = draftActivity($pengaju);
    Storage::disk('local')->put('activities/lpj.pdf', '%PDF');

    expect(fn () => activities()->submitLpj($pengaju, $draft, 'activities/lpj.pdf', 'LPJ.pdf', 10))->toThrow(AuthorizationException::class);

    $approved = approvedActivity($pengaju, $team);
    $teman = member(RoleName::PengajuKegiatan, ['organization_id' => $pengaju->organization_id]);

    expect(fn () => activities()->submitLpj($teman, $approved, 'activities/lpj.pdf', 'LPJ.pdf', 10))->toThrow(AuthorizationException::class);

    actingAs($pengaju);
    livewire(ViewStudentActivity::class, ['record' => $draft->getRouteKey()])->assertActionHidden('submitLpj');
    livewire(ViewStudentActivity::class, ['record' => $approved->getRouteKey()])->assertActionVisible('submitLpj');
});

it('penyetuju tidak boleh penanggung jawab/pembuat; tahapnya diambil alih Ketua/Wakil', function (): void {
    $team = reviewTeam();
    $activity = draftActivity($team['sekretaris']); // kegiatan internal diajukan Sekretaris
    activities()->submitProposal($team['sekretaris'], $activity);

    expect($activity->fresh()->organization->isBem())->toBeTrue()
        ->and(fn () => activities()->review($team['sekretaris'], $activity->fresh(), 'approve'))
        ->toThrow(AuthorizationException::class, 'tidak boleh mereview');

    activities()->review($team['ketua'], $activity->fresh(), 'approve');      // cadangan tahap Sekretaris
    activities()->review($team['bendahara'], $activity->fresh(), 'approve');
    activities()->review($team['wakil'], $activity->fresh(), 'approve');

    expect($activity->fresh()->status)->toBe(ActivityStatus::Approved)
        ->and($activity->fresh()->event->category)->toBe(EventCategory::Bem);
});

it('Ketua yang mengajukan diputuskan oleh Wakil', function (): void {
    $team = reviewTeam();
    $activity = draftActivity($team['ketua']);
    activities()->submitProposal($team['ketua'], $activity);
    activities()->review($team['sekretaris'], $activity->fresh(), 'approve');
    activities()->review($team['bendahara'], $activity->fresh(), 'approve');

    expect(fn () => activities()->review($team['ketua'], $activity->fresh(), 'approve'))->toThrow(AuthorizationException::class);

    activities()->review($team['wakil'], $activity->fresh(), 'approve');
    expect($activity->fresh()->status)->toBe(ActivityStatus::Approved);
});

it('pesan jelas bila tidak ada penyetuju lain yang aktif', function (): void {
    $ketua = superAdmin();
    member(RoleName::Sekretaris);
    member(RoleName::Bendahara);

    expect(fn () => activities()->submitProposal($ketua, draftActivity($ketua)))
        ->toThrow(ValidationException::class, 'Tidak ada penyetuju aktif untuk tahap Ketua/Wakil');
});

it('proposal wajib berkas PDF; berkas hanya bisa diunduh yang berhak', function (): void {
    reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $tanpaBerkas = draftActivity($pengaju, ['proposal_path' => null, 'proposal_name' => null]);

    expect(fn () => activities()->submitProposal($pengaju, $tanpaBerkas))->toThrow(ValidationException::class, 'proposal');

    $activity = draftActivity($pengaju);
    $url = route('panel.activities.file', [$activity, 'proposal']);
    $teman = member(RoleName::PengajuKegiatan, ['organization_id' => $pengaju->organization_id]);

    actingAs($pengaju)->get($url)->assertOk()->assertDownload('Proposal Turnamen.pdf');
    actingAs($teman)->get($url)->assertOk();                                   // satu organisasi
    actingAs(member(RoleName::Sekretaris))->get($url)->assertOk();
    actingAs(member(RoleName::PengajuKegiatan))->get($url)->assertNotFound();  // organisasi lain
    actingAs(member(RoleName::AnggotaMedpub))->get($url)->assertNotFound();
});

it('visibilitas: organisasi, pengurus pembuat, koordinator divisi pelaksana (read-only), reviewer & pembina', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan);
    $external = draftActivity($pengaju);
    $anggota = member(RoleName::AnggotaKajian);
    $internal = draftActivity($anggota, ['division_id' => divisionId('kajian')]);
    $koorKajian = member(RoleName::KoordinatorKajian);
    $koorHumas = member(RoleName::KoordinatorHumas);

    expect(StudentActivity::query()->visibleTo($pengaju)->pluck('id')->all())->toBe([$external->id])
        ->and(StudentActivity::query()->visibleTo($anggota)->pluck('id')->all())->toBe([$internal->id])
        ->and(StudentActivity::query()->visibleTo($koorKajian)->pluck('id')->all())->toBe([$internal->id])
        ->and(StudentActivity::query()->visibleTo($koorHumas)->pluck('id')->all())->toBe([])
        ->and(Gate::forUser($koorKajian)->allows('update', $internal))->toBeFalse();

    foreach ([RoleName::Sekretaris, RoleName::Bendahara, RoleName::Pembina, RoleName::SuperAdmin] as $role) {
        expect(StudentActivity::query()->visibleTo(member($role))->count())->toBe(2);
    }
});

it('pengurus BEM mengajukan kegiatan internal: organisasi BEM dan divisi tidak bisa dipalsukan pengaju', function (): void {
    $staff = member(RoleName::AnggotaHumas);
    $internal = draftActivity($staff, ['organization_id' => Organization::factory()->create()->id, 'division_id' => divisionId('humas')]);

    expect($internal->organization->name)->toBe(Organization::BEM_NAME)->and($internal->division_id)->toBe(divisionId('humas'));

    $pengaju = member(RoleName::PengajuKegiatan);
    $external = draftActivity($pengaju, ['organization_id' => Organization::bem()->id, 'division_id' => divisionId('bph')]);

    expect($external->organization_id)->toBe($pengaju->organization_id)->and($external->division_id)->toBeNull();
});

it('pembatalan menghapus agenda kalender; alasan wajib', function (): void {
    $team = reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $activity = approvedActivity($pengaju, $team);
    $eventId = $activity->event_id;

    expect(fn () => activities()->cancel($pengaju, $activity, ''))->toThrow(ValidationException::class)
        ->and(fn () => activities()->cancel(member(RoleName::Bendahara), $activity, 'Batal saja.'))->toThrow(AuthorizationException::class);

    activities()->cancel($pengaju, $activity, 'Lapangan tidak tersedia.');

    expect($activity->fresh()->status)->toBe(ActivityStatus::Cancelled)
        ->and(Event::find($eventId))->toBeNull();
});

it('peringatan bentrok kalender saat mengajukan; tetap boleh diajukan', function (): void {
    reviewTeam();
    app(EventService::class)->create(superAdmin(), [
        'title' => 'Pentas Seni', 'category' => 'ukm', 'starts_at' => now()->addDays(20)->toDateString(),
    ]);
    $pengaju = member(RoleName::PengajuKegiatan);
    $activity = draftActivity($pengaju);

    expect(activities()->conflictsFor($activity)->pluck('title')->all())->toBe(['Pentas Seni']);

    actingAs($pengaju);
    livewire(ViewStudentActivity::class, ['record' => $activity->getRouteKey()])
        ->callAction('submitProposal')
        ->assertActionMounted('confirmSubmitProposal')
        ->callMountedAction();

    expect($activity->fresh()->status)->toBe(ActivityStatus::ProposalSubmitted);
});

it('saat disetujui, agenda kalender bentrok ikut ditandai', function (): void {
    $team = reviewTeam();
    $pentas = app(EventService::class)->create(superAdmin(), ['title' => 'Pentas', 'category' => 'ukm', 'starts_at' => now()->addDays(20)->toDateString()]);

    $activity = approvedActivity(member(RoleName::PengajuKegiatan), $team);

    expect($activity->event->has_conflict)->toBeTrue()->and($pentas->fresh()->has_conflict)->toBeTrue();
});

it('titik kait sanksi LPJ terlambat dipanggil saat proposal diajukan (belum aktif)', function (): void {
    reviewTeam();
    $pengaju = member(RoleName::PengajuKegiatan);
    $mock = Mockery::mock(LpjSanctionPolicy::class);
    $mock->shouldReceive('blocksNewProposal')->once()->andReturn('Masih ada LPJ terlambat.');
    app()->instance(LpjSanctionPolicy::class, $mock);

    expect(fn () => activities()->submitProposal($pengaju, draftActivity($pengaju)))
        ->toThrow(ValidationException::class, 'Masih ada LPJ terlambat.');
});
