<?php

use App\Enums\ApplicantStatus;
use App\Enums\RoleName;
use App\Filament\Resources\RecruitmentApplicants\Pages\ListRecruitmentApplicants;
use App\Filament\Resources\RecruitmentApplicants\Pages\ViewRecruitmentApplicant;
use App\Filament\Resources\RecruitmentApplicants\RecruitmentApplicantResource;
use App\Filament\Resources\Recruitments\Pages\ManageRecruitments;
use App\Filament\Resources\Recruitments\RecruitmentResource;
use App\Filament\Widgets\RecruitmentByDivisionChart;
use App\Filament\Widgets\RecruitmentDailyChart;
use App\Filament\Widgets\RecruitmentStatusChart;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Models\Setting;
use App\Models\User;
use App\Services\RecruitmentService;
use App\Support\SpreadsheetExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Storage::fake('local');
});

function oprec(): RecruitmentService
{
    return app(RecruitmentService::class);
}

/** Pendaftar sampai tahap interview. */
function interviewing(array $attributes = []): RecruitmentApplicant
{
    return RecruitmentApplicant::factory()->status(ApplicantStatus::Interview)->create([
        'interview_at' => now()->addDay(),
        'interview_location' => 'Ruang 101',
        ...$attributes,
    ]);
}

it('perpindahan status mengikuti alur dan tercatat', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $a = RecruitmentApplicant::factory()->create();

    expect(fn () => oprec()->scheduleInterview($sekretaris, $a, '2026-11-20 10:00', 'Ruang 1'))->toThrow(ValidationException::class);
    expect(fn () => oprec()->decide($sekretaris, $a, true, divisionId('kajian')))->toThrow(ValidationException::class);

    oprec()->passDocuments($sekretaris, $a);
    oprec()->scheduleInterview($sekretaris, $a->refresh(), '2026-11-20 10:00', 'Ruang 1');
    expect($a->refresh()->status)->toBe(ApplicantStatus::Interview)
        ->and($a->interview_location)->toBe('Ruang 1');

    oprec()->scheduleInterview($sekretaris, $a, '2026-11-21 09:00', 'https://meet.example/abc');
    expect($a->refresh()->status)->toBe(ApplicantStatus::Interview)->and($a->interview_at->format('Y-m-d H:i'))->toBe('2026-11-21 09:00');

    oprec()->decide($sekretaris, $a, false, null, 'Kurang siap');
    expect($a->refresh()->status)->toBe(ApplicantStatus::Rejected)
        ->and($a->logs()->pluck('to_status')->map->value->all())->toBe(['document_passed', 'interview', 'rejected']);

    expect(fn () => oprec()->passDocuments($sekretaris, $a))->toThrow(ValidationException::class);
});

it('divisi diterima hanya dari pilihan 1 atau 2', function (): void {
    $admin = superAdmin();
    $a = interviewing(['first_choice_division_id' => divisionId('kajian'), 'second_choice_division_id' => divisionId('humas')]);

    expect(fn () => oprec()->decide($admin, $a, true, divisionId('medpub')))->toThrow(ValidationException::class);
    expect(fn () => oprec()->decide($admin, $a, true, divisionId('bph')))->toThrow(ValidationException::class);

    oprec()->decide($admin, $a, true, divisionId('humas'));
    expect($a->refresh()->status)->toBe(ApplicantStatus::Accepted)->and($a->accepted_division_id)->toBe(divisionId('humas'));
});

it('koordinator hanya menilai pendaftar yang memilih divisinya; nilai 1–5 satu per pewawancara', function (): void {
    $koorKajian = member(RoleName::KoordinatorKajian);
    $koorMedpub = member(RoleName::KoordinatorMedpub);
    $anggota = member(RoleName::AnggotaKajian);
    $a = interviewing(['first_choice_division_id' => divisionId('humas'), 'second_choice_division_id' => divisionId('kajian')]);

    oprec()->score($koorKajian, $a, 4, 'Komunikatif');
    oprec()->score($koorKajian, $a, 5, 'Setelah diskusi ulang');
    expect($a->interviews()->count())->toBe(1)->and($a->averageScore())->toBe(5.0);

    expect(fn () => oprec()->score($koorMedpub, $a, 3))->toThrow(AuthorizationException::class);
    expect(fn () => oprec()->score($anggota, $a, 3))->toThrow(AuthorizationException::class);
    expect(fn () => oprec()->score($koorKajian, $a, 6))->toThrow(ValidationException::class);

    oprec()->score(member(RoleName::Sekretaris), $a, 3);
    expect($a->averageScore())->toBe(4.0);

    // Koordinator tidak bisa mengubah status (hanya pengelola oprec).
    expect(fn () => oprec()->decide($koorKajian, $a, true, divisionId('kajian')))->toThrow(AuthorizationException::class);

    // Setelah final, nilai terkunci — juga bagi super_admin.
    oprec()->decide(superAdmin(), $a, false);
    expect(fn () => oprec()->score(superAdmin(), $a->refresh(), 3))->toThrow(AuthorizationException::class);
});

it('daftar pendaftar koordinator dibatasi divisinya; anggota, pengaju, dan bendahara tidak punya akses', function (): void {
    $kajian = RecruitmentApplicant::factory()->create(['name' => 'Pendaftar Kajian', 'first_choice_division_id' => divisionId('kajian')]);
    $second = RecruitmentApplicant::factory()->create(['name' => 'Pilihan Dua Medpub', 'first_choice_division_id' => divisionId('humas'), 'second_choice_division_id' => divisionId('medpub')]);
    $humas = RecruitmentApplicant::factory()->create(['name' => 'Pendaftar Humas', 'first_choice_division_id' => divisionId('humas')]);

    actingAs(member(RoleName::KoordinatorMedpub));
    livewire(ListRecruitmentApplicants::class)->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$kajian, $humas]);
    get(RecruitmentApplicantResource::getUrl('view', ['record' => $kajian]))->assertNotFound();
    get(RecruitmentApplicantResource::getUrl('view', ['record' => $second]))->assertOk()->assertDontSee($second->phone)->assertDontSee($second->email);

    actingAs(member(RoleName::Sekretaris));
    livewire(ListRecruitmentApplicants::class)->assertCanSeeTableRecords([$kajian, $second, $humas]);
    get(RecruitmentApplicantResource::getUrl('view', ['record' => $kajian]))->assertOk()->assertSee($kajian->phone);

    foreach ([RoleName::AnggotaKajian, RoleName::AnggotaMedpub, RoleName::Bendahara, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get(RecruitmentApplicantResource::getUrl('index'))->assertForbidden();
        get(RecruitmentResource::getUrl('index'))->assertForbidden();
    }
});

it('Pembina hanya melihat: tanpa tombol aksi, tanpa kontak, tanpa export', function (): void {
    $a = RecruitmentApplicant::factory()->create();
    actingAs(member(RoleName::Pembina));

    get(RecruitmentApplicantResource::getUrl('index'))->assertOk();
    get(RecruitmentResource::getUrl('index'))->assertOk();
    get(RecruitmentApplicantResource::getUrl('view', ['record' => $a]))->assertOk()->assertDontSee($a->phone);

    livewire(ViewRecruitmentApplicant::class, ['record' => $a->getRouteKey()])
        ->assertActionHidden('passDocuments')->assertActionHidden('reject')->assertActionHidden('score');
    livewire(ListRecruitmentApplicants::class)->assertActionHidden('exportXlsx');
    livewire(ManageRecruitments::class)->assertActionHidden('create');
});

it('aksi seleksi dari halaman detail (Sekretaris)', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $a = RecruitmentApplicant::factory()->create(['first_choice_division_id' => divisionId('kajian')]);
    actingAs($sekretaris);

    livewire(ViewRecruitmentApplicant::class, ['record' => $a->getRouteKey()])
        ->callAction('passDocuments')
        ->callAction('scheduleInterview', ['interview_at' => '2026-11-25 13:00', 'interview_location' => 'Sekretariat BEM'])
        ->callAction('score', ['score' => 4, 'note' => 'Baik'])
        ->callAction('accept', ['accepted_division_id' => divisionId('kajian')])
        ->assertHasNoActionErrors();

    expect($a->refresh()->status)->toBe(ApplicantStatus::Accepted)->and($a->averageScore())->toBe(4.0);
});

it('berkas CV & foto hanya untuk yang berhak melihat pendaftar (404 untuk lainnya)', function (): void {
    Storage::disk('local')->put('recruitment/cv/a.pdf', '%PDF-1.4');
    Storage::disk('local')->put('recruitment/photos/a.webp', 'webp');
    $a = RecruitmentApplicant::factory()->create([
        'first_choice_division_id' => divisionId('kajian'),
        'cv_path' => 'recruitment/cv/a.pdf', 'cv_name' => 'cv.pdf', 'photo_path' => 'recruitment/photos/a.webp',
    ]);

    actingAs(member(RoleName::KoordinatorKajian));
    get(route('panel.recruitment.file', [$a, 'cv']))->assertOk()->assertDownload();
    get(route('panel.recruitment.file', [$a, 'photo']))->assertOk()->assertHeader('Content-Type', 'image/webp');

    foreach ([RoleName::KoordinatorMedpub, RoleName::AnggotaKajian, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get(route('panel.recruitment.file', [$a, 'cv']))->assertNotFound();
        get(route('panel.recruitment.file', [$a, 'photo']))->assertNotFound();
    }

    auth()->logout();
    get(route('panel.recruitment.file', [$a, 'cv']))->assertRedirect();
});

it('"Jadikan akun pengurus" membuat user NONAKTIF tanpa role di divisi diterima', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $a = interviewing(['name' => 'Putri Ayu', 'email' => 'putri@contoh.test', 'phone' => '081200001111', 'second_choice_division_id' => divisionId('medpub')]);

    expect(fn () => oprec()->createStaffAccount($sekretaris, $a))->toThrow(ValidationException::class);

    oprec()->decide($sekretaris, $a, true, divisionId('medpub'));
    $user = oprec()->createStaffAccount($sekretaris, $a->refresh());

    expect($user->is_active)->toBeFalse()
        ->and($user->roles)->toHaveCount(0)
        ->and($user->division_id)->toBe(divisionId('medpub'))
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->email)->toBe('putri@contoh.test')
        ->and($user->phone)->toBe('081200001111')
        ->and($a->refresh()->user_id)->toBe($user->id);

    expect(fn () => oprec()->createStaffAccount($sekretaris, $a))->toThrow(ValidationException::class);
    expect(fn () => oprec()->createStaffAccount(member(RoleName::KoordinatorMedpub), $a))->toThrow(AuthorizationException::class);

    // Email yang sudah dipakai → pesan jelas, tidak membuat akun.
    $b = interviewing(['email' => $sekretaris->email]);
    oprec()->decide($sekretaris, $b, true, divisionId('kajian'));
    expect(fn () => oprec()->createStaffAccount($sekretaris, $b->refresh()))->toThrow(ValidationException::class, 'sudah dipakai');
    expect(User::where('email', $sekretaris->email)->count())->toBe(1);
});

it('data pribadi pendaftar tidak diterima dihapus 6 bulan setelah gelombang ditutup', function (): void {
    $this->travelTo('2026-11-16 10:00:00');
    $old = Recruitment::factory()->closed('2026-05-10 23:59:00')->create();
    $recent = Recruitment::factory()->closed('2026-06-20 23:59:00')->create();

    $make = function (Recruitment $wave, ApplicantStatus $status, string $key) {
        Storage::disk('local')->put("recruitment/cv/{$key}.pdf", '%PDF');
        Storage::disk('local')->put("recruitment/photos/{$key}.webp", 'img');

        return RecruitmentApplicant::factory()->status($status)->create([
            'recruitment_id' => $wave->id, 'cv_path' => "recruitment/cv/{$key}.pdf", 'cv_name' => 'cv.pdf',
            'photo_path' => "recruitment/photos/{$key}.webp", 'name' => "Nama {$key}",
        ]);
    };

    $rejected = $make($old, ApplicantStatus::Rejected, 'rej');
    $unprocessed = $make($old, ApplicantStatus::Interview, 'unp');
    $accepted = $make($old, ApplicantStatus::Accepted, 'acc');
    $tooRecent = $make($recent, ApplicantStatus::Rejected, 'new');

    artisan('recruitment:purge-personal-data')->expectsOutputToContain('2 pendaftar')->assertSuccessful();

    foreach ([$rejected, $unprocessed] as $a) {
        $a->refresh();
        expect($a->phone)->toBeNull()->and($a->email)->toBeNull()->and($a->cv_path)->toBeNull()->and($a->photo_path)->toBeNull()
            ->and($a->data_purged_at)->not->toBeNull()
            ->and($a->name)->not->toBeNull()->and($a->nim)->not->toBeNull()->and($a->prodi)->not->toBeNull()
            ->and($a->batch_year)->not->toBeNull()->and($a->first_choice_division_id)->not->toBeNull();
    }
    Storage::disk('local')->assertMissing(['recruitment/cv/rej.pdf', 'recruitment/photos/rej.webp', 'recruitment/cv/unp.pdf']);

    expect($accepted->refresh()->email)->not->toBeNull()->and($tooRecent->refresh()->email)->not->toBeNull();
    Storage::disk('local')->assertExists(['recruitment/cv/acc.pdf', 'recruitment/cv/new.pdf']);

    // Masa simpan bisa diatur lewat setting; tidak memproses ulang yang sudah dihapus.
    Setting::set('recruitment_retention_months', 4);
    artisan('recruitment:purge-personal-data')->expectsOutputToContain('1 pendaftar')->assertSuccessful();
});

it('export memuat nilai rata-rata, tanpa catatan internal, dan aman dari formula injection', function (): void {
    $a = interviewing(['name' => '=HYPERLINK("http://jahat.test","klik")', 'nim' => '2025000001']);
    $a->interviews()->create(['interviewer_id' => superAdmin()->id, 'score' => 4, 'note' => 'CATATAN-INTERNAL']);
    $a->interviews()->create(['interviewer_id' => member(RoleName::KoordinatorKajian)->id, 'score' => 3, 'note' => 'CATATAN-LAIN']);

    actingAs(member(RoleName::Sekretaris));
    $component = livewire(ListRecruitmentApplicants::class)->callAction('exportCsv');

    $download = $component->effects['download'] ?? null;
    expect($download)->not->toBeNull();
    $csv = base64_decode($download['content']);

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain('3.5')
        ->and($csv)->not->toContain('CATATAN-INTERNAL')
        ->and($csv)->not->toContain('CATATAN-LAIN');

    expect(SpreadsheetExport::sanitize('+628123'))->toBe("'+628123")
        ->and(SpreadsheetExport::sanitize('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(SpreadsheetExport::sanitize('-'))->toBe('-');

    actingAs(member(RoleName::KoordinatorKajian));
    livewire(ListRecruitmentApplicants::class)->assertActionHidden('exportCsv');
});

it('gelombang: jadwal aktif tidak boleh beririsan; hasil diumumkan sekali; berisi pendaftar tidak bisa dihapus', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $existing = Recruitment::factory()->create(['opens_at' => '2026-12-01 08:00', 'closes_at' => '2026-12-14 23:59']);
    actingAs($sekretaris);

    livewire(ManageRecruitments::class)->callAction('create', [
        'name' => 'Gelombang Bentrok', 'opens_at' => '2026-12-10 08:00', 'closes_at' => '2026-12-20 23:59',
        'open_divisions' => [(string) divisionId('kajian')], 'is_active' => true,
    ])->assertHasActionErrors(['closes_at']);

    livewire(ManageRecruitments::class)->callAction('create', [
        'name' => 'Gelombang 2', 'opens_at' => '2027-01-05 08:00', 'closes_at' => '2027-01-20 23:59',
        'open_divisions' => [(string) divisionId('kajian'), (string) divisionId('humas')], 'is_active' => true,
    ])->assertHasNoActionErrors();

    $new = Recruitment::where('name', 'Gelombang 2')->sole();
    expect($new->open_divisions)->toBe([divisionId('kajian'), divisionId('humas')])->and($new->created_by)->toBe($sekretaris->id);

    RecruitmentApplicant::factory()->create(['recruitment_id' => $existing->id]);
    expect($sekretaris->can('delete', $existing))->toBeFalse()
        ->and(superAdmin()->can('delete', $existing))->toBeFalse()
        ->and($sekretaris->can('delete', $new))->toBeTrue();

    oprec()->publishResults($sekretaris, $existing);
    expect($existing->refresh()->resultsPublished())->toBeTrue();
    expect(fn () => oprec()->publishResults(superAdmin(), $existing))->toThrow(AuthorizationException::class);
});

it('widget oprec: pengelola & koordinator (lingkup divisinya), tidak untuk anggota/pengaju; tampilan kosong rapi', function (): void {
    Recruitment::factory()->create();

    foreach ([RoleName::Sekretaris, RoleName::Pembina, RoleName::KoordinatorHumas] as $role) {
        actingAs(member($role));
        expect(RecruitmentStatusChart::canView())->toBeTrue();
        livewire(RecruitmentStatusChart::class)->assertSee('Belum ada pendaftar');
    }

    foreach ([RoleName::AnggotaHumas, RoleName::Bendahara, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        expect(RecruitmentByDivisionChart::canView())->toBeFalse();
    }

    RecruitmentApplicant::factory()->count(2)->create(['recruitment_id' => Recruitment::first()->id, 'first_choice_division_id' => divisionId('kajian')]);
    RecruitmentApplicant::factory()->create(['recruitment_id' => Recruitment::first()->id, 'first_choice_division_id' => divisionId('humas')]);

    actingAs(member(RoleName::KoordinatorHumas));
    livewire(RecruitmentStatusChart::class)->assertOk()->assertDontSee('Belum ada pendaftar');
    livewire(RecruitmentByDivisionChart::class)->assertOk();
    livewire(RecruitmentDailyChart::class)->assertOk();
    expect((new class extends RecruitmentStatusChart
    {
        public function total(): int
        {
            return $this->applicantsQuery()->count();
        }
    })->total())->toBe(1);
});
