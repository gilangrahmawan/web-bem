<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Enums\FormStatus;
use App\Enums\QuestionType;
use App\Enums\ScoreVisibility;
use App\Filament\Resources\Forms\FormResource;
use App\Models\Division;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\FormSection;
use App\Models\User;
use App\Policies\FormPolicy;
use App\Services\Forms\DestructiveChangeException;
use App\Services\Forms\FormAnswerValidator;
use App\Services\Forms\FormBuilderService;
use App\Services\Forms\FormExport;
use App\Services\Forms\FormResponseService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Pembuat form bergaya Google Form (docs/SPEC-FASE-11.md A4): tab Pertanyaan · Jawaban · Pengaturan.
 * Simpan otomatis tiap kolom selesai diubah; perubahan merusak (form sudah berjawaban) minta konfirmasi.
 * Pembina hanya melihat (semua aksi ubah diperiksa ulang lewat FormPolicy).
 *
 * @property Form $record
 */
class FormBuilder extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FormResource::class;

    protected string $view = 'filament.resources.forms.builder';

    #[Url(as: 'tab', except: 'pertanyaan')]
    public string $tab = 'pertanyaan';

    /** @var array{title?: string, description?: ?string} */
    public array $formData = [];

    /** @var array<int|string, array<string, mixed>> */
    public array $questionData = [];

    /** @var array<int|string, array{title: ?string, description: ?string}> */
    public array $sectionData = [];

    /** Kunci jawaban singkat, satu per baris. @var array<int|string, string> */
    public array $keyTexts = [];

    /** @var array<string, mixed>|null */
    public ?array $settingsData = [];

    public bool $onlyPending = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless($this->user()->can('view', $this->record), 404);

        $this->fillState();
        $this->settingsForm->fill($this->settingsState());
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    public function getBreadcrumbs(): array
    {
        return [FormResource::getUrl() => 'Form & Survei', 'Pembuat form'];
    }

    private function user(): User
    {
        return FormResource::user() ?? abort(403);
    }

    public function canEdit(): bool
    {
        return $this->user()->can('update', $this->record);
    }

    private function authorizeEdit(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    private function fillState(): void
    {
        $form = $this->record->refresh();
        $this->formData = ['title' => $form->title, 'description' => $form->description];
        $this->sectionData = $form->sections()->get()->mapWithKeys(fn (FormSection $s) => [$s->getKey() => ['title' => $s->title, 'description' => $s->description]])->all();
        $this->questionData = [];
        $this->keyTexts = [];

        foreach ($form->questions()->get() as $question) {
            $this->fillQuestion($question);
        }
    }

    private function fillQuestion(FormQuestion $q): void
    {
        $this->questionData[$q->getKey()] = [
            'type' => $q->type->value,
            'label' => $q->label,
            'help_text' => $q->help_text,
            'is_required' => $q->is_required,
            'options' => [...FormBuilderService::defaultOptions($q->type), ...($q->options ?? [])],
            'validation' => $q->validation ?? [],
            'points' => $q->points,
            'correct_answer' => $q->correct_answer ?? ['choices' => [], 'texts' => []],
        ];
        $this->keyTexts[$q->getKey()] = implode("\n", $q->correct_answer['texts'] ?? []);
    }

    private function question(int $id): FormQuestion
    {
        return $this->record->questions()->whereKey($id)->firstOrFail();
    }

    // ---------------------------------------------------------------- Pertanyaan

    public function updatedFormData(): void
    {
        $this->authorizeEdit();
        app(FormBuilderService::class)->updateDetails($this->record, [
            'title' => $this->formData['title'] ?? '',
            'description' => mb_substr(trim((string) ($this->formData['description'] ?? '')), 0, 3000) ?: null,
        ], $this->user());
        $this->formData['title'] = $this->record->title;
    }

    public function updatedQuestionData(mixed $value, string $key): void
    {
        $this->saveQuestion((int) explode('.', $key)[0]);
    }

    public function updatedKeyTexts(mixed $value, string $key): void
    {
        $id = (int) $key;
        $this->questionData[$id]['correct_answer']['texts'] = preg_split('/\R/', (string) $value) ?: [];
        $this->saveQuestion($id);
    }

    public function updatedSectionData(mixed $value, string $key): void
    {
        $this->authorizeEdit();
        $id = (int) explode('.', $key)[0];
        $section = $this->record->sections()->whereKey($id)->firstOrFail();
        app(FormBuilderService::class)->updateSection($section, $this->sectionData[$id] ?? []);
    }

    public function saveQuestion(int $id, bool $confirmed = false, ?array $data = null): void
    {
        $this->authorizeEdit();
        $question = $this->question($id);
        $data ??= $this->questionData[$id] ?? [];

        try {
            app(FormBuilderService::class)->updateQuestion($question, $data, $confirmed);
        } catch (DestructiveChangeException) {
            $this->fillQuestion($question); // kembalikan dulu; diterapkan bila dikonfirmasi
            $this->mountAction('confirmDestructive', ['op' => 'update', 'question' => $id, 'data' => $data]);

            return;
        }

        $this->fillQuestion($question->refresh());
    }

    public function addQuestion(?int $after = null, string $type = 'multiple_choice', ?int $section = null): void
    {
        $this->authorizeEdit();
        $question = app(FormBuilderService::class)->addQuestion(
            $this->record,
            QuestionType::tryFrom($type) ?? QuestionType::MultipleChoice,
            $after,
            $section && $this->record->sections()->whereKey($section)->exists() ? $section : null,
        );
        $this->fillState();
        $this->dispatch('form-question-added', id: $question->getKey());
    }

    public function addSection(): void
    {
        $this->authorizeEdit();
        app(FormBuilderService::class)->addSection($this->record);
        $this->fillState();
    }

    public function deleteSection(int $id): void
    {
        $this->authorizeEdit();

        try {
            app(FormBuilderService::class)->deleteSection($this->record->sections()->whereKey($id)->firstOrFail());
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }

        $this->fillState();
    }

    public function duplicateQuestion(int $id): void
    {
        $this->authorizeEdit();
        app(FormBuilderService::class)->duplicateQuestion($this->question($id));
        $this->fillState();
    }

    public function deleteQuestion(int $id, bool $confirmed = false): void
    {
        $this->authorizeEdit();

        try {
            app(FormBuilderService::class)->deleteQuestion($this->question($id), $confirmed);
        } catch (DestructiveChangeException) {
            $this->mountAction('confirmDestructive', ['op' => 'delete', 'question' => $id]);

            return;
        }

        $this->fillState();
    }

    public function addChoice(int $id): void
    {
        $choices = $this->questionData[$id]['options']['choices'] ?? [];
        $n = count($choices) + 1;

        while (in_array("Opsi {$n}", $choices, true)) {
            $n++;
        }

        $this->questionData[$id]['options']['choices'][] = "Opsi {$n}";
        $this->saveQuestion($id);
    }

    public function removeChoice(int $id, int $index): void
    {
        $choices = $this->questionData[$id]['options']['choices'] ?? [];
        array_splice($choices, $index, 1);
        $this->questionData[$id]['options']['choices'] = $choices;
        $this->saveQuestion($id);
    }

    /** @param  list<string>  $items  urutan "s:ID"/"q:ID" hasil drag & drop */
    public function reorder(array $items): void
    {
        $this->authorizeEdit();
        app(FormBuilderService::class)->reorder($this->record, array_map('strval', $items));
        $this->fillState();
    }

    public function confirmDestructiveAction(): Action
    {
        return Action::make('confirmDestructive')
            ->requiresConfirmation()
            ->color('danger')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading('Perubahan ini memengaruhi jawaban yang sudah masuk')
            ->modalDescription(fn (array $arguments): string => ($arguments['op'] ?? '') === 'delete'
                ? 'Pertanyaan akan hilang dari form. Jawaban lama tetap ada di export (ditandai "dihapus"), tetapi tidak tampil di ringkasan. Lanjutkan?'
                : 'Mengganti jenis pertanyaan atau menghapus/mengubah teks opsi membuat jawaban lama tidak cocok lagi dengan pertanyaan ini. Lanjutkan?')
            ->modalSubmitActionLabel('Ya, lanjutkan')
            ->action(function (array $arguments): void {
                $id = (int) ($arguments['question'] ?? 0);

                ($arguments['op'] ?? '') === 'delete'
                    ? $this->deleteQuestion($id, confirmed: true)
                    : $this->saveQuestion($id, confirmed: true, data: (array) ($arguments['data'] ?? []));
            });
    }

    // ---------------------------------------------------------------- Pengaturan

    /** @return array<string, mixed> */
    private function settingsState(): array
    {
        $form = $this->record;
        $settings = $form->allSettings();

        return [
            'is_quiz' => $form->is_quiz,
            'division_id' => $form->division_id,
            'opens_at' => $form->opens_at,
            'closes_at' => $form->closes_at,
            ...$settings,
            'retention_months' => $settings['retention_months'] === null ? 'forever' : (string) $settings['retention_months'],
        ];
    }

    public function settingsForm(Schema $schema): Schema
    {
        return $schema->statePath('settingsData')->disabled(fn (): bool => ! $this->canEdit())->components([
            Section::make('Umum')->columns(2)->schema([
                Toggle::make('is_quiz')->label('Jadikan kuis')->live()->columnSpanFull()
                    ->helperText('Pertanyaan bisa diberi poin dan kunci jawaban; nilai dihitung otomatis.'),
                Select::make('division_id')->label('Divisi pemilik')->placeholder('BEM (umum)')
                    ->options(fn () => Division::query()->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn (): bool => FormPolicy::managesAll($this->user())),
                DateTimePicker::make('opens_at')->label('Dibuka mulai')->seconds(false)->native(false)
                    ->helperText('Kosongkan untuk langsung dibuka saat terbit.'),
                DateTimePicker::make('closes_at')->label('Ditutup pada')->seconds(false)->native(false)->after('opens_at'),
                Textarea::make('confirmation_message')->label('Pesan setelah mengirim')->rows(2)->maxLength(1000)->columnSpanFull(),
            ]),
            Section::make('Responden')->columns(2)->schema([
                Toggle::make('single_submission')->label('Batasi satu kali isi')
                    ->helperText('Tanpa login: lewat cookie, dan email unik bila ada pertanyaan email wajib.'),
                Toggle::make('require_login')->label('Wajib login (khusus pengurus BEM aktif)'),
                Toggle::make('shuffle_questions')->label('Acak urutan pertanyaan per peserta'),
                Toggle::make('shuffle_options')->label('Acak urutan opsi per peserta'),
            ]),
            Section::make('Kuis')->columns(2)->visible(fn (Get $get): bool => (bool) $get('is_quiz'))->schema([
                TextInput::make('time_limit_minutes')->label('Batas waktu (menit)')->numeric()->minValue(1)->maxValue(600)
                    ->helperText('Kosongkan bila tanpa batas waktu. Dihitung di server.'),
                TextInput::make('pass_score')->label('Nilai lulus')->numeric()->minValue(0),
                Select::make('score_visibility')->label('Tampilkan nilai ke peserta')->required()
                    ->options(ScoreVisibility::options()),
            ]),
            Section::make('Penyimpanan data')->schema([
                Select::make('retention_months')->label('Hapus jawaban otomatis')->required()
                    ->options(['3' => '3 bulan setelah ditutup', '6' => '6 bulan setelah ditutup', '12' => '12 bulan setelah ditutup (bawaan)', '24' => '24 bulan setelah ditutup', 'forever' => 'Simpan selamanya'])
                    ->helperText('Jawaban & berkas peserta dihapus permanen oleh sistem setelah jangka waktu ini.'),
            ]),
        ]);
    }

    public function saveSettings(): void
    {
        $this->authorizeEdit();
        $state = $this->settingsForm->getState();

        app(FormBuilderService::class)->updateDetails($this->record, [
            'is_quiz' => (bool) ($state['is_quiz'] ?? false),
            'opens_at' => $state['opens_at'] ?? null,
            'closes_at' => $state['closes_at'] ?? null,
            ...(array_key_exists('division_id', $state) ? ['division_id' => $state['division_id']] : []),
            'settings' => collect($state)->only(array_keys(Form::DEFAULT_SETTINGS))->all(),
        ], $this->user());

        $this->settingsForm->fill($this->settingsState());
        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }

    // ---------------------------------------------------------------- Header

    protected function getHeaderActions(): array
    {
        $form = $this->record;
        $url = $form->publicUrl();

        return [
            Action::make('preview')->label('Pratinjau')->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(route('panel.forms.preview', $form->getKey()))->openUrlInNewTab(),
            Action::make('copyLink')->label('Salin link')->icon(Heroicon::OutlinedLink)->color('gray')
                ->alpineClickHandler('navigator.clipboard ? navigator.clipboard.writeText('.json_encode($url).').then(() => new FilamentNotification().title(\'Link disalin\').success().send()) : window.prompt(\'Salin link form:\', '.json_encode($url).')'),
            Action::make('downloadQr')->label('Unduh QR (PNG)')->icon(Heroicon::OutlinedQrCode)->color('gray')
                ->url(route('panel.forms.qr', $form->getKey())),
            Action::make('publish')->label($form->status === FormStatus::Closed ? 'Buka kembali' : 'Terbitkan')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $this->canEdit() && $this->record->status !== FormStatus::Published)
                ->requiresConfirmation()
                ->modalHeading('Terbitkan form?')
                ->modalDescription('Siapa pun yang memiliki link bisa mengisi form ini sesuai pengaturan.')
                ->action(function (): void {
                    $this->authorizeEdit();

                    try {
                        app(FormBuilderService::class)->publish($this->record, $this->user());
                    } catch (ValidationException $e) {
                        Notification::make()->title('Form belum bisa diterbitkan')
                            ->body(new HtmlString(collect($e->errors())->flatten()->map(fn ($m) => e($m))->join('<br>')))
                            ->danger()->persistent()->send();

                        return;
                    }

                    Notification::make()->title('Form terbit')->body('Bagikan link atau QR kepada responden.')->success()->send();
                }),
            Action::make('close')->label('Tutup form')->icon(Heroicon::OutlinedLockClosed)->color('danger')
                ->visible(fn (): bool => $this->canEdit() && $this->record->status === FormStatus::Published)
                ->requiresConfirmation()
                ->modalHeading('Tutup form?')
                ->modalDescription('Form berhenti menerima jawaban. Jawaban yang sudah masuk tetap tersimpan sesuai pengaturan penyimpanan data.')
                ->action(function (): void {
                    $this->authorizeEdit();
                    app(FormBuilderService::class)->close($this->record, $this->user());
                    Notification::make()->title('Form ditutup')->success()->send();
                }),
        ];
    }

    // ---------------------------------------------------------------- Jawaban

    public function viewResponseAction(): Action
    {
        return Action::make('viewResponse')
            ->modalHeading(fn (array $arguments): string => 'Jawaban #'.($arguments['no'] ?? ''))
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(function (array $arguments) {
                $response = $this->record->responses()->whereNotNull('submitted_at')->whereKey((int) ($arguments['response'] ?? 0))
                    ->with(['answers', 'respondent'])->firstOrFail();

                return view('filament.resources.forms.response-detail', [
                    'response' => $response,
                    'columns' => FormExport::columns($this->record),
                    'answers' => $response->answers->keyBy('form_question_id'),
                    'isQuiz' => $this->record->is_quiz,
                    'canGrade' => $this->user()->can('grade', $this->record),
                ]);
            });
    }

    public function gradeAnswerAction(): Action
    {
        return Action::make('gradeAnswer')
            ->modalHeading('Beri nilai')
            ->fillForm(fn (array $arguments): array => ($a = $this->answerFor($arguments)) ? ['points' => $a->points_awarded, 'note' => $a->grader_note] : [])
            ->schema(fn (array $arguments): array => [
                TextInput::make('points')->label('Poin (maks. '.($this->answerFor($arguments)?->question->points ?? 0).')')
                    ->numeric()->required()->minValue(0)->maxValue($this->answerFor($arguments)?->question->points ?? 0),
                Textarea::make('note')->label('Catatan penilai (internal)')->rows(2)->maxLength(2000),
            ])
            ->modalSubmitActionLabel('Simpan nilai')
            ->action(function (array $arguments, array $data): void {
                abort_unless($this->user()->can('grade', $this->record), 403);
                $answer = $this->answerFor($arguments) ?? abort(404);
                abort_unless($answer->question->type->isManuallyGraded(), 422);
                app(FormResponseService::class)->grade($answer, (float) $data['points'], $data['note'] ?? null, $this->user());
                Notification::make()->title('Nilai disimpan')->success()->send();
            });
    }

    private function answerFor(array $arguments): ?FormAnswer
    {
        return FormAnswer::query()->whereKey((int) ($arguments['answer'] ?? 0))
            ->whereHas('response', fn ($q) => $q->where('form_id', $this->record->getKey())->whereNotNull('submitted_at'))
            ->with('question')->first();
    }

    /** Ringkasan per pertanyaan untuk tab Jawaban. @return list<array<string, mixed>> */
    private function summary(Collection $answers): array
    {
        $result = [];

        foreach ($this->record->questions()->get() as $q) {
            if (! $q->type->acceptsAnswer()) {
                continue;
            }

            $list = $answers->get($q->getKey(), collect());
            $item = ['label' => $q->label, 'type' => $q->type, 'count' => $list->filter(fn (FormAnswer $a) => filled($a->value) || filled($a->files))->count()];

            if ($q->type->hasOptions() || $q->type === QuestionType::LinearScale) {
                $buckets = [];

                if ($q->type === QuestionType::LinearScale) {
                    [$min, $max] = FormAnswerValidator::scaleRange($q);

                    for ($n = $min; $n <= $max; $n++) {
                        $buckets[(string) $n] = 0;
                    }
                } else {
                    $buckets = array_fill_keys($q->choices(), 0);

                    if ($q->allowsOther()) {
                        $buckets['Lainnya'] = 0;
                    }
                }

                foreach ($list as $answer) {
                    $values = is_array($answer->value) ? ($answer->value['selected'] ?? []) : [$answer->value];

                    foreach ($values as $v) {
                        $label = $v === FormAnswerValidator::OTHER ? 'Lainnya' : (string) $v;

                        if (array_key_exists($label, $buckets)) {
                            $buckets[$label]++;
                        }
                    }
                }

                $item['buckets'] = $buckets;
                $item['correct'] = $this->record->is_quiz ? ($q->correct_answer['choices'] ?? []) : [];
            } else {
                $item['samples'] = $list->filter(fn (FormAnswer $a) => filled($a->value) || filled($a->files))->sortByDesc('id')->take(5)
                    ->map(fn (FormAnswer $a) => FormExport::display($q, $a))->values()->all();
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function getViewData(): array
    {
        $form = $this->record;
        $questions = $form->questions()->get();
        $sections = $form->sections()->get();
        $firstSection = $sections->first()?->getKey();

        $data = [
            'form' => $form,
            'canEdit' => $this->canEdit(),
            'publicUrl' => $form->publicUrl(),
            'sections' => $sections->map(fn (FormSection $s) => [
                'id' => $s->getKey(),
                'questions' => $questions->filter(fn (FormQuestion $q) => ($q->form_section_id ?? $firstSection) === $s->getKey())->values(),
            ])->all(),
            'types' => QuestionType::cases(),
            'patterns' => FormAnswerValidator::PATTERN_LABELS,
            'fileKinds' => FormBuilderService::FILE_KINDS,
            'hasResponses' => $form->hasSubmittedResponses(),
        ];

        if ($this->tab === 'jawaban') {
            $responses = $form->responses()->whereNotNull('submitted_at')->with('respondent')->orderByDesc('submitted_at')->get();
            $answers = FormAnswer::query()->whereIn('form_response_id', $responses->pluck('id'))->get()->groupBy('form_question_id');
            $pass = $form->setting('pass_score');

            $data += [
                'responses' => $responses->when($this->onlyPending, fn ($c) => $c->filter(fn (FormResponse $r) => $r->graded_at === null))->take(200)->values(),
                'responseCount' => $responses->count(),
                'responseNumbers' => $responses->sortBy('submitted_at')->values()->mapWithKeys(fn ($r, $i) => [$r->getKey() => $i + 1])->all(),
                'summary' => $this->summary($answers),
                'stats' => $form->is_quiz && $responses->isNotEmpty() ? [
                    'avg' => round((float) $responses->avg('score'), 2),
                    'min' => (float) $responses->min('score'),
                    'max' => (float) $responses->max('score'),
                    'max_score' => (float) $responses->max('max_score'),
                    'passed' => $pass === null ? null : $responses->filter(fn ($r) => $r->score >= (float) $pass)->count(),
                    'pending' => $responses->whereNull('graded_at')->count(),
                ] : null,
                'canExport' => $this->user()->can('export', $form),
            ];
        }

        return $data;
    }
}
