<?php

use App\Enums\FormPurpose;
use App\Enums\RoleName;
use App\Livewire\PublicForm;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;
use App\Services\Forms\FormResponseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    RateLimiter::clear('form-submit:127.0.0.1');
});

function quizWithSecretKey(): Form
{
    return formWith([
        ['type' => 'short_text', 'label' => 'Ibu kota Jawa Timur?', 'points' => 5, 'correct_answer' => ['texts' => ['kunci-rahasia-xyz']]],
        ['type' => 'multiple_choice', 'label' => 'Pilih', 'options' => ['choices' => ['Opsi A', 'Opsi B']], 'points' => 5, 'correct_answer' => ['choices' => ['Opsi B']]],
    ], ['title' => 'Kuis Uji', 'is_quiz' => true]);
}

it('form terbit tampil di /form/{public_id} dengan noindex; draf 404; ID asal 404', function (): void {
    $form = formWith([['type' => 'short_text', 'label' => 'Nama lengkap']], ['title' => 'Survei Kepuasan']);

    get('/form/'.$form->public_id)->assertOk()->assertSee('Survei Kepuasan')->assertSee('Nama lengkap')
        ->assertSee('noindex', false);

    $draft = formWith([['type' => 'short_text']], state: 'draft');
    get('/form/'.$draft->public_id)->assertNotFound();
    get('/form/TIDAKADA99')->assertNotFound();
});

it('form ditutup atau belum dibuka menampilkan pesan, bukan pertanyaan', function (): void {
    $closed = formWith([['type' => 'short_text', 'label' => 'Pertanyaan rahasia']], state: 'closed');
    get('/form/'.$closed->public_id)->assertOk()->assertSee('sudah tidak menerima jawaban')->assertDontSee('Pertanyaan rahasia');

    $future = formWith([['type' => 'short_text', 'label' => 'Pertanyaan nanti']], ['opens_at' => now()->addDay()]);
    get('/form/'.$future->public_id)->assertOk()->assertSee('belum dibuka')->assertDontSee('Pertanyaan nanti');
});

it('kunci jawaban tidak pernah ada di HTML maupun payload Livewire', function (): void {
    $form = quizWithSecretKey();

    $html = get('/form/'.$form->public_id)->assertOk()->getContent();
    expect($html)->not->toContain('kunci-rahasia-xyz')->not->toContain('correct_answer');

    $component = livewire(PublicForm::class, ['publicId' => $form->public_id]);
    $payload = json_encode($component->snapshot).$component->html();
    expect($payload)->not->toContain('kunci-rahasia-xyz')->not->toContain('correct_answer');
});

it('alur isi & kirim: Turnstile lolos → jawaban tersimpan, terkunci, nilai sesuai pengaturan', function (): void {
    fakeTurnstile();
    $form = quizWithSecretKey();
    $form->forceFill(['settings' => [...$form->allSettings(), 'score_visibility' => 'after_submit']])->save();
    [$short, $mc] = $form->questions()->get()->all();

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set("answers.{$short->id}", 'Kunci-Rahasia-XYZ')
        ->set("answers.{$mc->id}.selected", 'Opsi A')
        ->set('turnstileToken', 'token-uji')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('stage', 'done')
        ->assertSee('Nilai Anda');

    $response = FormResponse::query()->sole();
    expect($response->isSubmitted())->toBeTrue()->and($response->score)->toBe(5.0)
        ->and($response->respondent_id)->toBeNull();
});

it('Turnstile gagal → tidak ada jawaban terkirim', function (): void {
    fakeTurnstile(false);
    $form = formWith([['type' => 'short_text']]);

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set('answers.'.formQuestion($form)->id, 'halo')
        ->set('turnstileToken', 'token-palsu')
        ->call('submit')
        ->assertHasErrors('turnstile')
        ->assertSet('stage', 'form');

    expect(FormResponse::query()->whereNotNull('submitted_at')->exists())->toBeFalse();
});

it('pertanyaan wajib kosong ditolak dan halaman pindah ke bagian berisi kesalahan', function (): void {
    fakeTurnstile();
    $form = formWith([['type' => 'short_text', 'is_required' => true]]);

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set('turnstileToken', 'ok')
        ->call('submit')
        ->assertHasErrors('answers.'.formQuestion($form)->id);

    expect(FormResponse::query()->whereNotNull('submitted_at')->exists())->toBeFalse();
});

it('pengiriman dibatasi 5 per IP per jam', function (): void {
    fakeTurnstile();
    $form = formWith([['type' => 'short_text']], settings: ['single_submission' => false]);
    $q = formQuestion($form);

    for ($i = 0; $i < 5; $i++) {
        livewire(PublicForm::class, ['publicId' => $form->public_id])
            ->set("answers.{$q->id}", "isi {$i}")->set('turnstileToken', 'ok')->call('submit')->assertSet('stage', 'done');
    }

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set("answers.{$q->id}", 'keenam')->set('turnstileToken', 'ok')->call('submit')
        ->assertHasErrors('form')->assertSet('stage', 'form');

    expect(FormResponse::query()->whereNotNull('submitted_at')->count())->toBe(5);
});

it('satu kali isi tanpa login lewat cookie', function (): void {
    $form = formWith([['type' => 'short_text']]);

    Livewire::withCookies(["form_done_{$form->public_id}" => '1'])
        ->test(PublicForm::class, ['publicId' => $form->public_id])
        ->assertSet('stage', 'already')
        ->assertSee('sudah mengisi');
});

it('wajib login: tamu, pengaju, dan Pembina diarahkan masuk; pengurus aktif bisa mengisi tanpa Turnstile, sekali saja', function (): void {
    $form = formWith([['type' => 'short_text']], settings: ['require_login' => true]);
    $q = formQuestion($form);

    livewire(PublicForm::class, ['publicId' => $form->public_id])->assertSet('stage', 'login');

    actingAs(member(RoleName::PengajuKegiatan));
    livewire(PublicForm::class, ['publicId' => $form->public_id])->assertSet('stage', 'login');

    actingAs(member(RoleName::Pembina));
    livewire(PublicForm::class, ['publicId' => $form->public_id])->assertSet('stage', 'login');

    $pengurus = member(RoleName::AnggotaHumas);
    actingAs($pengurus);
    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set("answers.{$q->id}", 'hadir')->call('submit')->assertHasNoErrors()->assertSet('stage', 'done');

    $response = FormResponse::query()->sole();
    expect($response->respondent_id)->toBe($pengurus->id);

    livewire(PublicForm::class, ['publicId' => $form->public_id])->assertSet('stage', 'already');
});

it('exit ticket tidak memakai Turnstile', function (): void {
    $form = formWith([['type' => 'short_text']], purpose: FormPurpose::ExitTicket);

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set('answers.'.formQuestion($form)->id, 'materi jelas')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('stage', 'done');
});

it('simpan otomatis membuat draf jawaban yang bisa dilanjutkan lewat cookie', function (): void {
    $form = formWith([['type' => 'short_text'], ['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B']]]]);
    [$text, $mc] = $form->questions()->get()->all();

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set("answers.{$text->id}", 'setengah jalan')
        ->set("answers.{$mc->id}.selected", 'B');

    $response = FormResponse::query()->sole();
    expect($response->isSubmitted())->toBeFalse()->and($response->answers()->count())->toBe(2);

    // Token resume tersimpan sebagai hash; melanjutkan dengan token mentah memulihkan jawaban.
    [$fresh, $token] = app(FormResponseService::class)->start($form);
    app(FormResponseService::class)->saveAnswer($fresh, $mc, ['selected' => 'A']);
    expect($fresh->resume_token)->toBe(hash('sha256', $token));

    Livewire::withCookies(["form_resume_{$form->public_id}" => $token])
        ->test(PublicForm::class, ['publicId' => $form->public_id])
        ->assertSet("answers.{$mc->id}.selected", 'A');
});

it('kuis berbatas waktu menampilkan layar Mulai; waktu habis → jawaban tersimpan otomatis dikirim', function (): void {
    $this->travelTo('2026-11-18 10:00:00');
    $form = formWith([['type' => 'short_text', 'is_required' => true]], ['is_quiz' => true], ['time_limit_minutes' => 5]);
    $q = formQuestion($form);

    $component = livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->assertSet('stage', 'start')
        ->call('begin')
        ->assertSet('stage', 'form')
        ->set("answers.{$q->id}", 'tersimpan');

    $this->travelTo('2026-11-18 10:06:00');
    $component->set("answers.{$q->id}", 'terlambat')->assertSet('stage', 'done');

    expect(FormAnswer::query()->sole()->value)->toBe('tersimpan')
        ->and(FormResponse::query()->sole()->isSubmitted())->toBeTrue();
});

it('upload berkas lewat form publik tersimpan privat; berkas berbahaya ditolak', function (): void {
    Storage::fake('local');
    $form = formWith([['type' => 'file_upload', 'options' => ['max_files' => 2, 'file_kinds' => ['image', 'pdf']]]]);
    $q = formQuestion($form);

    livewire(PublicForm::class, ['publicId' => $form->public_id])
        ->set("uploads.{$q->id}", [UploadedFile::fake()->image('ktm.jpg')])
        ->assertHasNoErrors()
        ->set("uploads.{$q->id}", [UploadedFile::fake()->create('shell.pdf', 1, 'text/x-php')])
        ->assertHasErrors("uploads.{$q->id}");

    $files = FormAnswer::query()->sole()->files;
    expect($files)->toHaveCount(1)->and($files[0]['mime'])->toBe('image/webp');
    Storage::disk('local')->assertExists($files[0]['path']);
});

it('pratinjau tidak pernah membuat form_responses (isi, unggah, kirim)', function (): void {
    Storage::fake('local');
    $form = formWith([
        ['type' => 'short_text', 'is_required' => true],
        ['type' => 'file_upload', 'options' => ['max_files' => 1, 'file_kinds' => ['image']]],
    ], state: 'draft');
    [$text, $file] = $form->questions()->get()->all();

    actingAs(superAdmin());
    get(route('panel.forms.preview', $form->id))->assertOk()->assertSee('Mode pratinjau');

    livewire(PublicForm::class, ['publicId' => $form->public_id, 'preview' => true])
        ->set("answers.{$text->id}", 'uji')
        ->set("uploads.{$file->id}", [UploadedFile::fake()->image('a.jpg')])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('stage', 'done');

    expect(FormResponse::query()->count())->toBe(0)->and(FormAnswer::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('forms'))->toBe([]);
});

it('pratinjau hanya untuk yang berhak melihat form', function (): void {
    $form = formWith([['type' => 'short_text']], ['division_id' => divisionId('kajian')], state: 'draft');

    actingAs(member(RoleName::KoordinatorHumas));
    get(route('panel.forms.preview', $form->id))->assertNotFound();

    actingAs(member(RoleName::KoordinatorKajian));
    get(route('panel.forms.preview', $form->id))->assertOk();
});

it('CSP halaman /form sama dengan CSP global; halaman lain tidak berubah dan kamera tidak dibuka', function (): void {
    $form = formWith([['type' => 'short_text']]);
    $formResponse = get('/form/'.$form->public_id)->assertOk();
    $csp = $formResponse->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeEmpty();

    foreach (['/', '/berita', '/aspirasi', '/kalender', '/oprec'] as $uri) {
        $other = get($uri)->assertOk();
        expect($other->headers->get('Content-Security-Policy'))->toBe($csp, "CSP {$uri} berbeda dari /form")
            ->and($other->headers->get('Permissions-Policy'))->toBe($formResponse->headers->get('Permissions-Policy'));
    }

    expect((string) $formResponse->headers->get('Permissions-Policy'))->not->toContain('camera=(self)');
});
