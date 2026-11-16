<?php

use App\Enums\ApplicantStatus;
use App\Models\ActivityLog;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Services\RecruitmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function (): void {
    Storage::fake('local');
    $GLOBALS['oprecUploadTemps'] = [];
});

afterEach(function (): void {
    foreach ($GLOBALS['oprecUploadTemps'] ?? [] as $path) {
        @unlink($path);
    }
});

/**
 * Berkas unggahan "sungguhan": MIME dideteksi dari ISI (finfo), bukan dari ekstensi seperti UploadedFile::fake(),
 * sehingga aturan mimetypes benar-benar teruji.
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $content);
    $GLOBALS['oprecUploadTemps'][] = $path;

    return new UploadedFile($path, $name, null, null, true);
}

function fakeCv(string $name = 'cv.pdf', int $kb = 50): UploadedFile
{
    return realUpload($name, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n".str_repeat('x', $kb * 1024)."\n%%EOF\n");
}

function oprecPayload(array $overrides = []): array
{
    return [
        'name' => 'Dimas Pratama',
        'nim' => '2025 1101 23',
        'prodi' => 'Informatika',
        'batch_year' => 2025,
        'phone' => '0812-3456-7890',
        'email' => 'Dimas@Contoh.test',
        'first_choice_division_id' => divisionId('kajian'),
        'second_choice_division_id' => divisionId('medpub'),
        'motivation' => 'Saya ingin belajar berorganisasi dan berkontribusi untuk mahasiswa UBHINUS.',
        'experience' => 'Ketua kelas dan panitia PKKMB.',
        'cv' => fakeCv(),
        'photo' => UploadedFile::fake()->image('foto.jpg', 600, 800),
        'consent' => '1',
        'cf-turnstile-response' => 'token-dari-widget',
        ...$overrides,
    ];
}

it('form oprec tertutup bila tidak ada gelombang dibuka (belum mulai / sudah lewat / nonaktif)', function (): void {
    fakeTurnstile();
    Recruitment::factory()->closed()->create();
    Recruitment::factory()->create(['is_active' => false]);
    Recruitment::factory()->create(['name' => 'Gelombang Depan', 'opens_at' => now()->addWeek(), 'closes_at' => now()->addWeeks(2)]);

    get('/oprec')->assertOk()->assertSee('Pendaftaran sedang ditutup')->assertSee('Gelombang Depan')->assertDontSee('Kirim pendaftaran');

    post('/oprec', oprecPayload())->assertRedirect('/oprec')->assertSessionHasErrors();
    expect(RecruitmentApplicant::count())->toBe(0);
});

it('pendaftaran berhasil: NIM dinormalisasi, kode REG, berkas di disk privat, tanpa IP', function (): void {
    fakeTurnstile();
    $wave = Recruitment::factory()->create();

    get('/oprec')->assertOk()->assertSee($wave->name)->assertSee('Kirim pendaftaran')->assertSee('dihapus otomatis');

    post('/oprec', oprecPayload())->assertRedirect('/oprec')->assertSessionHas('submitted_code');

    $a = RecruitmentApplicant::sole();
    expect($a->registration_code)->toMatch('/^REG-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
        ->and($a->nim)->toBe('2025110123')
        ->and($a->phone)->toBe('081234567890')
        ->and($a->email)->toBe('dimas@contoh.test')
        ->and($a->status)->toBe(ApplicantStatus::Registered)
        ->and($a->consent_at)->not->toBeNull()
        ->and($a->cv_path)->toStartWith('recruitment/cv/')->toEndWith('.pdf')
        ->and($a->cv_name)->toBe('cv.pdf')
        ->and($a->photo_path)->toEndWith('.webp')
        ->and(ActivityLog::where('action', 'recruitment.registered')->sole()->ip)->toBeNull();

    Storage::disk('local')->assertExists([$a->cv_path, $a->photo_path]);
    Storage::disk('public')->assertMissing($a->photo_path);
    get('/oprec')->assertSee($a->registration_code);
});

it('NIM hanya sekali per gelombang, tetapi boleh di gelombang lain', function (): void {
    fakeTurnstile();
    $old = Recruitment::factory()->closed()->create();
    RecruitmentApplicant::factory()->create(['recruitment_id' => $old->id, 'nim' => '2025110123']);
    Recruitment::factory()->create();

    post('/oprec', oprecPayload())->assertSessionHasNoErrors();
    post('/oprec', oprecPayload(['nim' => '2025110123', 'cv' => fakeCv(), 'photo' => UploadedFile::fake()->image('f.jpg')]))
        ->assertSessionHasErrors(['nim' => 'NIM ini sudah terdaftar pada gelombang ini.']);

    expect(RecruitmentApplicant::count())->toBe(2);
});

it('pilihan divisi harus yang dibuka dan pilihan 2 berbeda', function (): void {
    fakeTurnstile();
    Recruitment::factory()->create(['open_divisions' => [divisionId('kajian'), divisionId('humas')]]);

    post('/oprec', oprecPayload(['first_choice_division_id' => divisionId('medpub'), 'second_choice_division_id' => null]))
        ->assertSessionHasErrors('first_choice_division_id');
    post('/oprec', oprecPayload(['first_choice_division_id' => divisionId('bph'), 'second_choice_division_id' => null]))
        ->assertSessionHasErrors('first_choice_division_id');
    post('/oprec', oprecPayload(['second_choice_division_id' => divisionId('kajian')]))
        ->assertSessionHasErrors('second_choice_division_id');
    post('/oprec', oprecPayload(['second_choice_division_id' => '']))->assertSessionHasNoErrors();

    expect(RecruitmentApplicant::sole()->second_choice_division_id)->toBeNull();
});

it('berkas divalidasi: CV harus PDF asli ≤ 2 MB, foto gambar ≤ 2 MB', function (): void {
    fakeTurnstile();
    Recruitment::factory()->create();

    post('/oprec', oprecPayload(['cv' => fakeCv(kb: 2100)]))->assertSessionHasErrors('cv');
    post('/oprec', oprecPayload(['cv' => realUpload('cv.pdf', '<?php echo 1;')]))->assertSessionHasErrors('cv');
    post('/oprec', oprecPayload(['cv' => UploadedFile::fake()->image('cv.png')]))->assertSessionHasErrors('cv');
    post('/oprec', oprecPayload(['photo' => UploadedFile::fake()->image('foto.jpg')->size(2100)]))->assertSessionHasErrors('photo');
    post('/oprec', oprecPayload(['photo' => fakeCv('foto.jpg')]))->assertSessionHasErrors('photo');

    expect(RecruitmentApplicant::count())->toBe(0);
});

it('persetujuan data dan Turnstile wajib', function (): void {
    fakeTurnstile(success: false);
    Recruitment::factory()->create();

    post('/oprec', oprecPayload())->assertSessionHasErrors('cf-turnstile-response');
    post('/oprec', oprecPayload(['consent' => null]))->assertSessionHasErrors('consent');

    expect(RecruitmentApplicant::count())->toBe(0);
});

it('pendaftaran dibatasi 5 per IP per jam', function (): void {
    fakeTurnstile();
    Recruitment::factory()->create();
    RateLimiter::clear('public-submit');

    foreach (range(1, 5) as $i) {
        post('/oprec', oprecPayload(['nim' => "20251100{$i}0", 'cv' => fakeCv(), 'photo' => UploadedFile::fake()->image('f.jpg')]))->assertRedirect();
    }

    post('/oprec', oprecPayload(['nim' => '2025110999']))->assertTooManyRequests();
});

it('halaman hasil: kode + NIM wajib cocok; menampilkan jadwal interview tanpa nilai & catatan', function (): void {
    fakeTurnstile();
    $a = RecruitmentApplicant::factory()->status(ApplicantStatus::Interview)->create([
        'nim' => '2025110123',
        'interview_at' => '2026-11-20 13:30:00',
        'interview_location' => 'Sekretariat BEM, Gedung A',
    ]);
    $a->interviews()->create(['interviewer_id' => superAdmin()->id, 'score' => 2, 'note' => 'CATATAN-RAHASIA-PEWAWANCARA']);

    post('/oprec/hasil', ['registration_code' => $a->registration_code, 'nim' => '9999999999', 'cf-turnstile-response' => 't'])
        ->assertSessionHasErrors('registration_code');

    post('/oprec/hasil', ['registration_code' => strtolower($a->registration_code), 'nim' => '2025 110 123', 'cf-turnstile-response' => 't'])
        ->assertRedirect('/oprec/hasil');

    get('/oprec/hasil')->assertOk()
        ->assertSee('Tahap interview')->assertSee('20 November 2026')->assertSee('13.30')->assertSee('Sekretariat BEM, Gedung A')
        ->assertDontSee('CATATAN-RAHASIA-PEWAWANCARA')->assertDontSee('2 / 5')->assertDontSee('Nilai');
});

it('keputusan diterima/ditolak baru terlihat setelah hasil diumumkan', function (): void {
    fakeTurnstile();
    $admin = superAdmin();
    $wave = Recruitment::factory()->create();
    $service = app(RecruitmentService::class);

    $accepted = RecruitmentApplicant::factory()->create(['recruitment_id' => $wave->id, 'nim' => '2025000001']);
    $service->passDocuments($admin, $accepted);
    $service->scheduleInterview($admin, $accepted->refresh(), '2026-11-20 10:00', 'Ruang 101');
    $service->decide($admin, $accepted->refresh(), true, divisionId('kajian'));

    $earlyReject = RecruitmentApplicant::factory()->create(['recruitment_id' => $wave->id, 'nim' => '2025000002']);
    $service->decide($admin, $earlyReject, false);

    $check = function (RecruitmentApplicant $a) {
        post('/oprec/hasil', ['registration_code' => $a->registration_code, 'nim' => $a->nim, 'cf-turnstile-response' => 't']);

        return get('/oprec/hasil');
    };

    $check($accepted)->assertSee('Tahap interview')->assertDontSee('Selamat!')->assertSee('setelah diumumkan');
    $check($earlyReject)->assertSee('Berkas sedang diperiksa')->assertDontSee('Belum lolos');

    $service->publishResults($admin, $wave);

    $check($accepted)->assertSee('Diterima')->assertSee('Selamat!')->assertSee('Kajian');
    $check($earlyReject)->assertSee('Belum lolos');
});

it('cek hasil dibatasi 20 per IP per jam', function (): void {
    fakeTurnstile();
    RateLimiter::clear('public-status');

    foreach (range(1, 20) as $i) {
        post('/oprec/hasil', ['registration_code' => 'REG-AAAA-AAAA', 'nim' => '123', 'cf-turnstile-response' => 't'])->assertRedirect();
    }

    post('/oprec/hasil', ['registration_code' => 'REG-AAAA-AAAA', 'nim' => '123', 'cf-turnstile-response' => 't'])->assertTooManyRequests();
});
