<?php

use App\Enums\FormStatus;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormResponse;
use App\Services\Forms\DestructiveChangeException;
use App\Services\Forms\FormAnswerValidator;
use App\Services\Forms\FormBuilderService;
use App\Services\Forms\FormResponseService;
use App\Services\Forms\FormScoringService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function answerValidator(): FormAnswerValidator
{
    return app(FormAnswerValidator::class);
}

function formResponses(): FormResponseService
{
    return app(FormResponseService::class);
}

function expectInvalid(callable $fn): void
{
    expect($fn)->toThrow(ValidationException::class);
}

// ------------------------------------------------------------ Validasi 13 jenis

it('memvalidasi 13 jenis pertanyaan di server', function (): void {
    $form = formWith([
        ['type' => 'short_text', 'validation' => ['max_length' => 5, 'pattern' => 'digits']],
        ['type' => 'paragraph', 'validation' => ['min_length' => 3]],
        ['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B'], 'allow_other' => true]],
        ['type' => 'checkboxes', 'options' => ['choices' => ['A', 'B', 'C']], 'validation' => ['max_selected' => 2]],
        ['type' => 'dropdown', 'options' => ['choices' => ['X', 'Y']]],
        ['type' => 'number', 'validation' => ['min' => 1, 'max' => 10]],
        ['type' => 'email'],
        ['type' => 'phone'],
        ['type' => 'date'],
        ['type' => 'time'],
        ['type' => 'linear_scale', 'options' => ['scale_min' => 1, 'scale_max' => 5]],
        ['type' => 'file_upload', 'options' => ['max_files' => 1, 'file_kinds' => ['pdf']]],
        ['type' => 'text_block'],
    ]);
    [$short, $para, $mc, $cb, $dd, $num, $email, $phone, $date, $time, $scale, $file, $block] = $form->questions()->get()->all();

    expect(answerValidator()->validate($short, ' 123 '))->toBe('123');
    expectInvalid(fn () => answerValidator()->validate($short, '123456'));
    expectInvalid(fn () => answerValidator()->validate($short, 'abc'));

    expect(answerValidator()->validate($para, 'abcd'))->toBe('abcd');
    expectInvalid(fn () => answerValidator()->validate($para, 'ab'));

    expect(answerValidator()->validate($mc, ['selected' => 'A']))->toBe(['selected' => ['A'], 'other' => null]);
    expect(answerValidator()->validate($mc, ['selected' => '__other__', 'other' => 'Z']))->toBe(['selected' => ['__other__'], 'other' => 'Z']);
    expectInvalid(fn () => answerValidator()->validate($mc, ['selected' => 'Palsu']));
    expectInvalid(fn () => answerValidator()->validate($mc, ['selected' => ['A', 'B']]));
    expectInvalid(fn () => answerValidator()->validate($mc, ['selected' => '__other__', 'other' => '']));

    expect(answerValidator()->validate($cb, ['selected' => ['A', 'C']])['selected'])->toBe(['A', 'C']);
    expectInvalid(fn () => answerValidator()->validate($cb, ['selected' => ['A', 'B', 'C']]));
    expectInvalid(fn () => answerValidator()->validate($cb, ['selected' => ['__other__'], 'other' => 'x'])); // "Lainnya" tidak diizinkan

    expect(answerValidator()->validate($dd, ['selected' => 'Y'])['selected'])->toBe(['Y']);
    expectInvalid(fn () => answerValidator()->validate($dd, ['selected' => 'Z']));

    expect(answerValidator()->validate($num, '7'))->toBe(7);
    expectInvalid(fn () => answerValidator()->validate($num, '11'));
    expectInvalid(fn () => answerValidator()->validate($num, 'tujuh'));

    expect(answerValidator()->validate($email, 'Nama@Contoh.com'))->toBe('nama@contoh.com');
    expectInvalid(fn () => answerValidator()->validate($email, 'bukan-email'));

    expect(answerValidator()->validate($phone, '0812-3456-7890'))->toBe('081234567890');
    expect(answerValidator()->validate($phone, '+6281234567890'))->toBe('+6281234567890');
    expectInvalid(fn () => answerValidator()->validate($phone, '0212345678'));

    expect(answerValidator()->validate($date, '2026-12-31'))->toBe('2026-12-31');
    expectInvalid(fn () => answerValidator()->validate($date, '2026-02-30'));

    expect(answerValidator()->validate($time, '23:59'))->toBe('23:59');
    expectInvalid(fn () => answerValidator()->validate($time, '24:00'));

    expect(answerValidator()->validate($scale, '5'))->toBe(5);
    expectInvalid(fn () => answerValidator()->validate($scale, '6'));
    expectInvalid(fn () => answerValidator()->validate($scale, '0'));

    expect(answerValidator()->validate($file, 'apa saja'))->toBeNull()
        ->and(answerValidator()->validate($block, 'apa saja'))->toBeNull();
});

it('pertanyaan wajib ditolak bila kosong saat kirim, tetapi boleh kosong saat simpan otomatis', function (): void {
    $form = formWith([['type' => 'short_text', 'is_required' => true]]);
    $q = formQuestion($form);

    expectInvalid(fn () => answerValidator()->validate($q, '  '));
    expect(answerValidator()->validate($q, '', final: false))->toBeNull();
});

// ------------------------------------------------------------ Penilaian

it('menilai pilihan ganda, kotak centang (tepat & parsial sesuai rumus pemilik), dan jawaban singkat', function (): void {
    $form = formWith([
        ['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B']], 'points' => 2, 'correct_answer' => ['choices' => ['B']]],
        ['type' => 'checkboxes', 'options' => ['choices' => ['A', 'B', 'C', 'D']], 'points' => 4, 'correct_answer' => ['choices' => ['A', 'B']]],
        ['type' => 'checkboxes', 'options' => ['choices' => ['A', 'B', 'C', 'D'], 'partial_credit' => true], 'points' => 4, 'correct_answer' => ['choices' => ['A', 'B']]],
        ['type' => 'short_text', 'points' => 3, 'correct_answer' => ['texts' => ['Bung Hatta']]],
    ], ['is_quiz' => true]);
    [$mc, $exact, $partial, $short] = $form->questions()->get()->all();
    $scoring = app(FormScoringService::class);

    expect($scoring->autoScore($mc, ['selected' => ['B']]))->toBe(2.0)
        ->and($scoring->autoScore($mc, ['selected' => ['A']]))->toBe(0.0)
        ->and($scoring->autoScore($exact, ['selected' => ['B', 'A']]))->toBe(4.0)
        ->and($scoring->autoScore($exact, ['selected' => ['A']]))->toBe(0.0)
        // (benar dipilih − salah dipilih) ÷ jumlah jawaban benar × poin, minimal 0
        ->and($scoring->autoScore($partial, ['selected' => ['A']]))->toBe(2.0)
        ->and($scoring->autoScore($partial, ['selected' => ['A', 'B', 'C']]))->toBe(2.0)
        ->and($scoring->autoScore($partial, ['selected' => ['A', 'C', 'D']]))->toBe(0.0)
        ->and($scoring->autoScore($partial, ['selected' => ['A', 'B']]))->toBe(4.0)
        ->and($scoring->autoScore($short, '  bung   HATTA '))->toBe(3.0)
        ->and($scoring->autoScore($short, 'Soekarno'))->toBe(0.0);
});

it('kuis dengan esai menunggu penilaian manual; nilai total diperbarui setelah dinilai', function (): void {
    $form = formWith([
        ['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B']], 'points' => 5, 'correct_answer' => ['choices' => ['A']]],
        ['type' => 'paragraph', 'points' => 10],
    ], ['is_quiz' => true]);
    [$mc, $essay] = $form->questions()->get()->all();

    [$response] = formResponses()->start($form);
    $response = formResponses()->submit($response, [$mc->id => ['selected' => 'A'], $essay->id => 'Jawaban esai saya']);

    expect($response->score)->toBe(5.0)->and($response->max_score)->toBe(15.0)->and($response->graded_at)->toBeNull();

    $answer = $response->answers()->where('form_question_id', $essay->id)->first();
    formResponses()->grade($answer, 50, 'Bagus', superAdmin()); // dibatasi ke poin maksimal

    $response->refresh();
    expect($response->score)->toBe(15.0)->and($response->graded_at)->not->toBeNull()
        ->and($answer->refresh()->points_awarded)->toBe(10.0);
});

// ------------------------------------------------------------ Kirim, kunci, satu kali isi, batas waktu

it('jawaban terkunci setelah dikirim', function (): void {
    $form = formWith([['type' => 'short_text']]);
    $q = formQuestion($form);
    [$response] = formResponses()->start($form);
    formResponses()->submit($response, [$q->id => 'pertama']);

    expectInvalid(fn () => formResponses()->saveAnswer($response->refresh(), $q, 'ubah'));
    expectInvalid(fn () => formResponses()->submit($response, [$q->id => 'lagi']));
    expect(FormAnswer::query()->sole()->value)->toBe('pertama');
});

it('satu kali isi tanpa login: email yang sama (pertanyaan email wajib) ditolak kedua kalinya', function (): void {
    $form = formWith([['type' => 'email', 'is_required' => true]]);
    $q = formQuestion($form);

    [$first] = formResponses()->start($form);
    formResponses()->submit($first, [$q->id => 'budi@contoh.com']);

    [$second] = formResponses()->start($form);
    expectInvalid(fn () => formResponses()->submit($second, [$q->id => 'BUDI@contoh.com']));

    // Tanpa batas satu kali isi → boleh.
    $form->forceFill(['settings' => [...Form::DEFAULT_SETTINGS, 'single_submission' => false]])->save();
    expect(formResponses()->submit($second, [$q->id => 'budi@contoh.com'])->isSubmitted())->toBeTrue();
});

it('batas waktu kuis dihitung di server: lewat batas, jawaban baru ditolak dan respons difinalisasi dengan jawaban tersimpan', function (): void {
    $this->travelTo('2026-11-18 10:00:00');
    $form = formWith([['type' => 'short_text', 'is_required' => true]], ['is_quiz' => true], ['time_limit_minutes' => 10]);
    $q = formQuestion($form);

    [$response] = formResponses()->start($form);
    expect($response->expires_at->format('H:i'))->toBe('10:10');
    formResponses()->saveAnswer($response, $q, 'tersimpan');

    $this->travelTo('2026-11-18 10:12:00');
    expectInvalid(fn () => formResponses()->saveAnswer($response->refresh(), $q, 'terlambat'));

    $submitted = formResponses()->submit($response, [$q->id => 'terlambat']);
    expect($submitted->isSubmitted())->toBeTrue()
        ->and(FormAnswer::query()->sole()->value)->toBe('tersimpan');
});

it('urutan pertanyaan & opsi diacak per peserta dan dicatat (judul tetap di tempat)', function (): void {
    $questions = [['type' => 'text_block', 'label' => 'Petunjuk']];

    for ($i = 1; $i <= 12; $i++) {
        $questions[] = ['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B', 'C', 'D', 'E', 'F']]];
    }

    $form = formWith($questions, ['is_quiz' => true], ['shuffle_questions' => true, 'shuffle_options' => true]);
    $orders = collect(range(1, 4))->map(fn () => formResponses()->start($form)[0]->question_order);

    expect($orders->every(fn ($o) => $o['questions'][0] === formQuestion($form, 0)->id))->toBeTrue()
        ->and($orders->pluck('questions')->unique(fn ($q) => implode(',', $q))->count())->toBeGreaterThan(1)
        ->and($orders->first()['options'])->toHaveCount(12);
});

// ------------------------------------------------------------ Upload

it('upload: maks. 3 berkas, gambar jadi WebP privat, dokumen disimpan privat, ZIP & berkas palsu ditolak', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $form = formWith([['type' => 'file_upload', 'options' => ['max_files' => 3, 'file_kinds' => ['image', 'pdf', 'word', 'excel', 'powerpoint']]]]);
    $q = formQuestion($form);
    [$response] = formResponses()->start($form);

    $pdfPath = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($pdfPath, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $pdf = new UploadedFile($pdfPath, 'proposal.pdf', null, null, true);
    $image = UploadedFile::fake()->image('foto.jpg', 800, 600);

    $answer = formResponses()->storeFiles($response, $q, [$pdf, $image]);

    expect($answer->files)->toHaveCount(2)
        ->and($answer->files[1]['mime'])->toBe('image/webp')
        ->and($answer->files[1]['name'])->toBe('foto.webp');
    Storage::disk('local')->assertExists($answer->files[0]['path']);
    Storage::disk('local')->assertExists($answer->files[1]['path']);
    expect(Storage::disk('public')->allFiles())->toBe([]);

    $zipPath = tempnam(sys_get_temp_dir(), 'zip');
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::OVERWRITE);
    $zip->addFromString('a.txt', 'isi');
    $zip->close();

    expectInvalid(fn () => formResponses()->storeFiles($response, $q, [new UploadedFile($zipPath, 'arsip.zip', null, null, true)]));

    $phpPath = tempnam(sys_get_temp_dir(), 'php');
    file_put_contents($phpPath, '<?php echo "x";');
    expectInvalid(fn () => formResponses()->storeFiles($response, $q, [new UploadedFile($phpPath, 'cv.pdf', null, null, true)]));

    // Batas 3 berkas.
    expectInvalid(fn () => formResponses()->storeFiles($response, $q, [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]));
    expect(formResponses()->storeFiles($response, $q, [UploadedFile::fake()->image('c.png')])->files)->toHaveCount(3);
});

it('upload gambar > 2 MB dan dokumen > 10 MB ditolak', function (): void {
    Storage::fake('local');
    $form = formWith([['type' => 'file_upload', 'options' => ['max_files' => 3, 'file_kinds' => ['image', 'pdf']]]]);
    $q = formQuestion($form);

    expectInvalid(fn () => formResponses()->validateFile($q, UploadedFile::fake()->image('besar.jpg')->size(2049)));
    expectInvalid(fn () => formResponses()->validateFile($q, UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')));
});

// ------------------------------------------------------------ Pembuat form

it('perubahan merusak pada form berjawaban butuh konfirmasi; pengubahan aman langsung tersimpan', function (): void {
    $form = formWith([['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B']]]]);
    $q = formQuestion($form);
    $builder = app(FormBuilderService::class);

    // Belum ada jawaban → bebas.
    $builder->updateQuestion($q, ['options' => ['choices' => ['A', 'B', 'C']]]);

    [$response] = formResponses()->start($form);
    formResponses()->submit($response, [$q->id => ['selected' => 'A']]);

    expect(fn () => $builder->updateQuestion($q->refresh(), ['type' => 'paragraph']))->toThrow(DestructiveChangeException::class)
        ->and(fn () => $builder->updateQuestion($q->refresh(), ['options' => ['choices' => ['A', 'B']]]))->toThrow(DestructiveChangeException::class)
        ->and(fn () => $builder->deleteQuestion($q->refresh()))->toThrow(DestructiveChangeException::class);

    $builder->updateQuestion($q->refresh(), ['label' => 'Judul baru', 'options' => ['choices' => ['A', 'B', 'C', 'D']]]);
    expect($q->refresh()->label)->toBe('Judul baru');

    $builder->updateQuestion($q, ['type' => 'paragraph'], confirmed: true);
    expect($q->refresh()->type)->toBe(QuestionType::Paragraph);
});

it('urutan drag & drop memindahkan pertanyaan antar bagian', function (): void {
    $form = formWith([['type' => 'short_text'], ['type' => 'short_text'], ['type' => 'short_text']], state: 'draft');
    $builder = app(FormBuilderService::class);
    $s2 = $builder->addSection($form);
    [$a, $b, $c] = $form->questions()->get()->all();

    $builder->reorder($form, ["q:{$c->id}", "s:{$s2->id}", "q:{$a->id}", "q:{$b->id}"]);

    expect($form->questions()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
        ->and($a->refresh()->form_section_id)->toBe($s2->id)
        ->and($c->refresh()->form_section_id)->toBe($form->sections()->first()->id);
});

it('terbit ditolak bila pertanyaan belum lengkap atau kuis berpoin tanpa kunci', function (): void {
    $form = formWith([['type' => 'multiple_choice', 'label' => '', 'options' => ['choices' => []]]], state: 'draft');
    expectInvalid(fn () => app(FormBuilderService::class)->publish($form, superAdmin()));

    $quiz = formWith([['type' => 'multiple_choice', 'options' => ['choices' => ['A']], 'points' => 1]], ['is_quiz' => true], state: 'draft');
    expectInvalid(fn () => app(FormBuilderService::class)->publish($quiz, superAdmin()));

    $ok = formWith([['type' => 'short_text']], state: 'draft');
    app(FormBuilderService::class)->publish($ok, superAdmin());
    expect($ok->refresh()->status)->toBe(FormStatus::Published);
});

it('data pertanyaan dibersihkan: kunci jawaban hanya dari opsi yang ada, maks. 3 berkas, tanpa ZIP, pola dari daftar aman', function (): void {
    $form = formWith([['type' => 'multiple_choice', 'options' => ['choices' => ['A']]]], ['is_quiz' => true], state: 'draft');
    $q = formQuestion($form);
    $builder = app(FormBuilderService::class);

    $builder->updateQuestion($q, ['options' => ['choices' => ['A', 'B', 'A', '']], 'correct_answer' => ['choices' => ['Z', 'B']]]);
    expect($q->refresh()->choices())->toBe(['A', 'B'])->and($q->correct_answer)->toBe(['choices' => ['B']]);

    $builder->updateQuestion($q, ['type' => 'file_upload', 'options' => ['max_files' => 9, 'file_kinds' => ['archive', 'pdf']]]);
    expect($q->refresh()->options)->toBe(['max_files' => 3, 'file_kinds' => ['pdf']]);

    $builder->updateQuestion($q, ['type' => 'short_text', 'validation' => ['pattern' => '/.*/']]);
    expect($q->refresh()->validation)->toBe([]);
});

it('koordinator selalu membuat form untuk divisinya sendiri; ID publik acak 10 karakter tanpa karakter ambigu', function (): void {
    $koordinator = member(RoleName::KoordinatorKajian);
    $form = app(FormBuilderService::class)->create($koordinator, ['title' => 'Survei', 'division_id' => divisionId('humas')]);

    expect($form->division_id)->toBe(divisionId('kajian'))
        ->and($form->public_id)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{10}$/')
        ->and($form->questions()->count())->toBe(1);
});

// ------------------------------------------------------------ Retensi

it('retensi: jawaban & berkas dihapus 12 bulan setelah ditutup; "simpan selamanya" dilewati; draf > 30 hari dibuang', function (): void {
    Storage::fake('local');
    $this->travelTo('2026-11-18 10:00:00');

    $old = formWith([['type' => 'file_upload', 'options' => ['max_files' => 1, 'file_kinds' => ['image']]]]);
    $forever = formWith([['type' => 'short_text']], settings: ['retention_months' => null]);
    $recent = formWith([['type' => 'short_text']]);

    [$r1] = formResponses()->start($old);
    $answer = formResponses()->storeFiles($r1, formQuestion($old), [UploadedFile::fake()->image('a.jpg')]);
    formResponses()->submit($r1, []);
    [$r2] = formResponses()->start($forever);
    formResponses()->submit($r2, [formQuestion($forever)->id => 'x']);
    [$r3] = formResponses()->start($recent);
    formResponses()->submit($r3, [formQuestion($recent)->id => 'y']);
    formResponses()->start($recent); // draf yang tidak dikirim

    $old->forceFill(['status' => FormStatus::Closed, 'closed_at' => '2025-11-01 00:00:00'])->save();
    $forever->forceFill(['status' => FormStatus::Closed, 'closed_at' => '2020-01-01 00:00:00'])->save();
    $recent->forceFill(['status' => FormStatus::Closed, 'closed_at' => '2026-01-01 00:00:00'])->save();

    $this->travelTo('2026-12-25 10:00:00');
    $this->artisan('forms:purge-responses')->assertSuccessful();

    expect(FormResponse::query()->where('form_id', $old->id)->exists())->toBeFalse()
        ->and(FormResponse::query()->where('form_id', $forever->id)->exists())->toBeTrue()
        ->and(FormResponse::query()->where('form_id', $recent->id)->whereNotNull('submitted_at')->exists())->toBeTrue()
        ->and(FormResponse::query()->whereNull('submitted_at')->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($answer->files[0]['path']);
});
