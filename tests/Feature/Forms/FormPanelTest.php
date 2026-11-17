<?php

use App\Enums\FormStatus;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Filament\Resources\Forms\Pages\FormBuilder;
use App\Filament\Resources\Forms\Pages\ListForms;
use App\Filament\Widgets\FormResponsesDailyChart;
use App\Filament\Widgets\FormStatusChart;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\Forms\FormResponseService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function submittedForm(array $questions, array $answers, array $attributes = []): Form
{
    $form = formWith($questions, $attributes);
    $service = app(FormResponseService::class);
    [$response] = $service->start($form);
    $mapped = [];

    foreach ($form->questions()->get()->values() as $i => $q) {
        if (array_key_exists($i, $answers)) {
            $mapped[$q->id] = $answers[$i];
        }
    }

    $service->submit($response, $mapped);

    return $form;
}

it('hak akses daftar form: Super Admin & Sekretaris semua, koordinator divisinya, Pembina lihat, anggota/bendahara/pengaju ditolak', function (): void {
    $kajian = formWith([], ['title' => 'Form Kajian', 'division_id' => divisionId('kajian')]);
    $humas = formWith([], ['title' => 'Form Humas', 'division_id' => divisionId('humas')]);
    $umum = formWith([], ['title' => 'Form Umum']);

    foreach ([superAdmin(), member(RoleName::Sekretaris), member(RoleName::Pembina)] as $user) {
        actingAs($user);
        livewire(ListForms::class)->assertCanSeeTableRecords([$kajian, $humas, $umum]);
    }

    actingAs(member(RoleName::KoordinatorKajian));
    livewire(ListForms::class)->assertCanSeeTableRecords([$kajian])->assertCanNotSeeTableRecords([$humas, $umum]);
    get(FormBuilder::getUrl(['record' => $humas]))->assertNotFound();
    get(FormBuilder::getUrl(['record' => $kajian]))->assertOk();

    foreach ([RoleName::AnggotaKajian, RoleName::Bendahara, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get('/panel-bem/form')->assertForbidden();
        get(FormBuilder::getUrl(['record' => $kajian]))->assertNotFound();
    }
});

it('koordinator membuat form lewat aksi "Buat form" → selalu milik divisinya, lalu diarahkan ke pembuat form', function (): void {
    actingAs(member(RoleName::KoordinatorMedpub));

    livewire(ListForms::class)
        ->callAction('create', ['title' => 'Survei Konten', 'is_quiz' => false])
        ->assertHasNoActionErrors()
        ->assertRedirect();

    $form = Form::query()->sole();
    expect($form->division_id)->toBe(divisionId('medpub'))->and($form->status)->toBe(FormStatus::Draft)
        ->and($form->questions()->count())->toBe(1);
});

it('Pembina bisa membuka pembuat form tetapi tidak bisa mengubah apa pun', function (): void {
    $form = formWith([['type' => 'short_text', 'label' => 'Asli']], state: 'draft');
    $q = formQuestion($form);
    actingAs(member(RoleName::Pembina));

    $page = livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->assertOk()->assertActionHidden('publish');

    $page->call('addQuestion')->assertForbidden();
    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->set("questionData.{$q->id}.label", 'Diubah')->assertForbidden();
    expect($q->refresh()->label)->toBe('Asli')->and($form->questions()->count())->toBe(1);
});

it('pembuat form: simpan otomatis label, tambah/gandakan/hapus pertanyaan, tambah opsi, ganti jenis', function (): void {
    $form = formWith([['type' => 'multiple_choice', 'options' => ['choices' => ['A']]]], state: 'draft');
    $q = formQuestion($form);
    actingAs(member(RoleName::Sekretaris));

    $page = livewire(FormBuilder::class, ['record' => $form->getRouteKey()])
        ->set("questionData.{$q->id}.label", 'Divisi favorit?')
        ->call('addChoice', $q->id)
        ->set("questionData.{$q->id}.is_required", true);

    $q->refresh();
    expect($q->label)->toBe('Divisi favorit?')->and($q->choices())->toBe(['A', 'Opsi 2'])->and($q->is_required)->toBeTrue();

    $page->call('addQuestion', $q->id, 'email')->call('duplicateQuestion', $q->id);
    expect($form->questions()->pluck('type')->map->value->all())->toBe(['multiple_choice', 'multiple_choice', 'email']);

    $page->set("questionData.{$q->id}.type", 'checkboxes');
    expect($q->refresh()->type)->toBe(QuestionType::Checkboxes)->and($q->choices())->toBe(['A', 'Opsi 2']);

    $email = $form->questions()->where('type', 'email')->first();
    $page->call('deleteQuestion', $email->id);
    expect($form->questions()->count())->toBe(2);

    $page->set('formData.title', 'Judul Baru');
    expect($form->refresh()->title)->toBe('Judul Baru');
});

it('perubahan merusak di form berjawaban memunculkan konfirmasi; tanpa konfirmasi tidak berubah', function (): void {
    $form = submittedForm([['type' => 'multiple_choice', 'options' => ['choices' => ['A', 'B']]]], [['selected' => 'A']]);
    $q = formQuestion($form);
    actingAs(superAdmin());

    $page = livewire(FormBuilder::class, ['record' => $form->getRouteKey()])
        ->set("questionData.{$q->id}.type", 'paragraph')
        ->assertActionMounted('confirmDestructive');

    expect($q->refresh()->type)->toBe(QuestionType::MultipleChoice);

    $page->callMountedAction();
    expect($q->refresh()->type)->toBe(QuestionType::Paragraph);
});

it('terbitkan & tutup form dari header; terbit gagal bila belum lengkap', function (): void {
    $form = formWith([['type' => 'short_text', 'label' => '']], state: 'draft');
    actingAs(superAdmin());

    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->callAction('publish');
    expect($form->refresh()->status)->toBe(FormStatus::Draft);

    formQuestion($form)->update(['label' => 'Nama']);
    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->callAction('publish');
    expect($form->refresh()->status)->toBe(FormStatus::Published);

    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->callAction('close');
    expect($form->refresh()->status)->toBe(FormStatus::Closed)->and($form->closed_at)->not->toBeNull();
});

it('pengaturan: retensi bawaan 12 bulan, bisa "simpan selamanya"; koordinator tidak bisa memindah divisi', function (): void {
    $form = formWith([], ['division_id' => divisionId('humas')], state: 'draft');
    actingAs(member(RoleName::KoordinatorHumas));

    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])
        ->assertSet('settingsData.retention_months', '12')
        ->set('settingsData.retention_months', 'forever')
        ->set('settingsData.division_id', divisionId('kajian'))
        ->set('settingsData.single_submission', false)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $form->refresh();
    expect($form->setting('retention_months'))->toBeNull()
        ->and($form->setting('single_submission'))->toBeFalse()
        ->and($form->division_id)->toBe(divisionId('humas'));
});

it('QR PNG & salin link tersedia bagi yang berhak; lainnya 404', function (): void {
    $form = formWith([], ['division_id' => divisionId('kajian')]);

    actingAs(member(RoleName::KoordinatorKajian));
    $png = get(route('panel.forms.qr', $form->id))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr($png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->assertActionVisible('copyLink')->assertActionVisible('downloadQr');

    actingAs(member(RoleName::KoordinatorHumas));
    get(route('panel.forms.qr', $form->id))->assertNotFound();
});

it('export CSV/XLSX aman formula injection; Pembina & divisi lain tidak bisa export', function (): void {
    $form = submittedForm([['type' => 'short_text', 'label' => 'Nama'], ['type' => 'checkboxes', 'label' => 'Minat', 'options' => ['choices' => ['A', 'B']]]],
        ['=HYPERLINK("http://jahat")', ['selected' => ['A', 'B']]], ['division_id' => divisionId('kajian')]);

    actingAs(member(RoleName::KoordinatorKajian));
    $csv = get(route('panel.forms.export', [$form->id, 'csv']))->assertOk();
    $content = file_get_contents($csv->baseResponse->getFile()->getPathname());
    expect($content)->toContain("'=HYPERLINK")->toContain('A, B')->toContain('Nama');
    get(route('panel.forms.export', [$form->id, 'xlsx']))->assertOk();

    actingAs(member(RoleName::Pembina));
    get(route('panel.forms.export', [$form->id, 'csv']))->assertNotFound();

    actingAs(member(RoleName::KoordinatorHumas));
    get(route('panel.forms.export', [$form->id, 'csv']))->assertNotFound();
});

it('berkas jawaban hanya bisa diunduh pengelola/Pembina form tersebut (404 untuk lainnya, termasuk menebak ID)', function (): void {
    Storage::fake('local');
    $form = formWith([['type' => 'file_upload', 'options' => ['max_files' => 1, 'file_kinds' => ['image']]]], ['division_id' => divisionId('kajian')]);
    $service = app(FormResponseService::class);
    [$response] = $service->start($form);
    $answer = $service->storeFiles($response, formQuestion($form), [UploadedFile::fake()->image('ktm.jpg')]);

    actingAs(superAdmin());
    get(route('panel.forms.answer-file', [$answer->id, 0]))->assertNotFound(); // belum dikirim

    $service->submit($response, []);
    get(route('panel.forms.answer-file', [$answer->id, 0]))->assertOk();
    get(route('panel.forms.answer-file', [$answer->id, 5]))->assertNotFound();

    actingAs(member(RoleName::Pembina));
    get(route('panel.forms.answer-file', [$answer->id, 0]))->assertOk();

    foreach ([RoleName::KoordinatorHumas, RoleName::AnggotaKajian, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        get(route('panel.forms.answer-file', [$answer->id, 0]))->assertNotFound();
    }
});

it('tab Jawaban: ringkasan & penilaian esai oleh pengelola; Pembina tidak bisa menilai', function (): void {
    $form = submittedForm([
        ['type' => 'multiple_choice', 'label' => 'Warna', 'options' => ['choices' => ['Merah', 'Biru']], 'points' => 1, 'correct_answer' => ['choices' => ['Biru']]],
        ['type' => 'paragraph', 'label' => 'Esai', 'points' => 10],
    ], [['selected' => 'Biru'], 'Jawaban esai'], ['is_quiz' => true]);
    $answer = FormAnswer::query()->where('form_question_id', formQuestion($form, 1)->id)->sole();

    actingAs(member(RoleName::Sekretaris));
    livewire(FormBuilder::class, ['record' => $form->getRouteKey()])
        ->set('tab', 'jawaban')
        ->assertSee('Warna')->assertSee('Perlu dinilai')
        ->callAction(TestAction::make('gradeAnswer')->arguments(['answer' => $answer->id]), ['points' => 8, 'note' => 'Cukup'])
        ->assertHasNoActionErrors();

    expect($answer->refresh()->points_awarded)->toBe(8.0)->and($answer->response->refresh()->score)->toBe(9.0);

    actingAs(member(RoleName::Pembina));
    $page = livewire(FormBuilder::class, ['record' => $form->getRouteKey()])->set('tab', 'jawaban')->assertSee('Warna');

    $page->callAction(TestAction::make('gradeAnswer')->arguments(['answer' => $answer->id]), ['points' => 1])->assertForbidden();
    expect($answer->refresh()->points_awarded)->toBe(8.0);
});

it('form berjawaban tidak bisa dihapus (juga oleh Super Admin); form tanpa jawaban bisa', function (): void {
    $withAnswers = submittedForm([['type' => 'short_text']], ['x']);
    $empty = formWith([['type' => 'short_text']]);
    actingAs(superAdmin());

    livewire(ListForms::class)->assertActionHidden(TestAction::make('delete')->table($withAnswers))
        ->callAction(TestAction::make('delete')->table($empty));

    expect(Form::query()->find($empty->id))->toBeNull()->and(Form::query()->find($withAnswers->id))->not->toBeNull();
});

it('widget form: pengelola & Pembina melihat; anggota & pengaju tidak; data mengikuti divisi', function (): void {
    $kajian = submittedForm([['type' => 'short_text']], ['a'], ['division_id' => divisionId('kajian')]);
    submittedForm([['type' => 'short_text']], ['b'], ['division_id' => divisionId('humas')]);

    foreach ([superAdmin(), member(RoleName::Sekretaris), member(RoleName::Pembina), member(RoleName::KoordinatorKajian)] as $user) {
        actingAs($user);
        expect(FormResponsesDailyChart::canView())->toBeTrue()->and(FormStatusChart::canView())->toBeTrue();
        livewire(FormResponsesDailyChart::class)->assertOk();
        livewire(FormStatusChart::class)->assertOk();
    }

    foreach ([RoleName::AnggotaKajian, RoleName::Bendahara, RoleName::PengajuKegiatan] as $role) {
        actingAs(member($role));
        expect(FormResponsesDailyChart::canView())->toBeFalse()->and(FormStatusChart::canView())->toBeFalse();
    }

    actingAs(member(RoleName::KoordinatorKajian));
    $data = (fn () => $this->getData())->call(new FormResponsesDailyChart);
    expect(array_sum($data['datasets'][0]['data']))->toBe(1);

    actingAs(member(RoleName::KoordinatorMedpub));
    expect((fn () => $this->getData())->call(new FormStatusChart))->toBe([]);
    expect($kajian->exists)->toBeTrue();
});
