<?php

namespace App\Livewire;

use App\Enums\FormPurpose;
use App\Enums\FormStatus;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Enums\ScoreVisibility;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\User;
use App\Services\Forms\FormAnswerValidator;
use App\Services\Forms\FormResponseService;
use App\Services\TurnstileVerifier;
use App\Support\Forms\ParticipantFormView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Halaman isi form publik /form/{public_id} (docs/SPEC-FASE-11.md A5) + pratinjau panel.
 * Pratinjau ($preview) memvalidasi semuanya tetapi TIDAK PERNAH membuat form_responses/berkas.
 * Tampilan hanya dari ParticipantFormView (tanpa kunci jawaban).
 */
class PublicForm extends Component
{
    use WithFileUploads;

    public const SUBMIT_PER_HOUR = 5;

    #[Locked]
    public string $publicId;

    #[Locked]
    public bool $preview = false;

    #[Locked]
    public ?int $responseId = null;

    /** start | form | done | closed | login | already */
    #[Locked]
    public string $stage = 'form';

    #[Locked]
    public ?array $result = null;

    /** @var array<int|string, mixed> */
    public array $answers = [];

    /** Upload sementara Livewire per pertanyaan. @var array<int|string, mixed> */
    public array $uploads = [];

    /** Pratinjau: nama berkas saja (tidak disimpan). @var array<int|string, list<string>> */
    #[Locked]
    public array $previewFiles = [];

    public int $page = 0;

    public string $turnstileToken = '';

    public function mount(string $publicId, bool $preview = false): void
    {
        $this->publicId = $publicId;
        $this->preview = $preview;
        $form = $this->form;

        if ($preview) {
            abort_unless(Gate::allows('view', $form), 404);
            $this->stage = $this->needsStartScreen($form) ? 'start' : 'form';

            return;
        }

        abort_if($form->status === FormStatus::Draft, 404);

        if (! $form->isAcceptingResponses()) {
            $this->stage = 'closed';

            return;
        }

        if ($form->setting('require_login') && ! self::isBoardMember(auth()->user())) {
            $this->stage = 'login';

            return;
        }

        if ($this->alreadySubmitted($form)) {
            $this->stage = 'already';

            return;
        }

        $response = app(FormResponseService::class)->findByToken($form, request()->cookie($this->cookieName('resume')));

        if ($response && ! $response->isSubmitted() && $this->ownsResponse($response)) {
            $this->responseId = $response->getKey();
            $this->answers = self::answersForInputs($form, $response);

            if ($response->isExpired()) {
                $this->finalize($response);

                return;
            }
        }

        $this->stage = ! $this->responseId && $this->needsStartScreen($form) ? 'start' : 'form';
    }

    /** Jawaban tersimpan → bentuk input (pilihan tunggal sebagai string, bukan array). @return array<int, mixed> */
    private static function answersForInputs(Form $form, FormResponse $response): array
    {
        $types = $form->questions()->pluck('type', 'id');
        $answers = [];

        foreach ($response->answers()->get() as $answer) {
            $value = $answer->value;
            $type = $types[$answer->form_question_id] ?? null;

            if ($value === null) {
                continue;
            }

            if (in_array($type, [QuestionType::MultipleChoice, QuestionType::Dropdown], true) && is_array($value)) {
                $value['selected'] = $value['selected'][0] ?? '';
            }

            $answers[$answer->form_question_id] = $value;
        }

        return $answers;
    }

    /** Peserta kuis berpindah tab/aplikasi: dicatat untuk pengelola (bukan otomatis didiskualifikasi). */
    public function tabSwitched(): void
    {
        $response = $this->response();

        if ($response && $this->form->is_quiz && ! $response->isSubmitted()) {
            $response->increment('tab_switches');
        }

        $this->skipRender();
    }

    /** Wajib login: hanya pengurus BEM aktif (bukan pengaju, bukan Pembina). */
    public static function isBoardMember(?User $user): bool
    {
        return $user !== null && $user->is_active && $user->roles()->exists()
            && ! $user->isApplicant() && ! $user->hasRole(RoleName::Pembina->value);
    }

    #[Computed(persist: false)]
    public function form(): Form
    {
        return Form::query()->where('public_id', $this->publicId)->firstOrFail();
    }

    private function response(): ?FormResponse
    {
        if ($this->preview || ! $this->responseId) {
            return null;
        }

        $response = FormResponse::query()->whereKey($this->responseId)->where('form_id', $this->form->getKey())->first();
        abort_unless($response, 404);

        return $response;
    }

    private function needsStartScreen(Form $form): bool
    {
        return $form->timeLimitMinutes() !== null || $form->setting('shuffle_questions') || $form->setting('shuffle_options');
    }

    private function cookieName(string $kind): string
    {
        return "form_{$kind}_{$this->publicId}";
    }

    private function alreadySubmitted(Form $form): bool
    {
        if (! $form->setting('single_submission')) {
            return false;
        }

        $user = auth()->user();

        if ($form->setting('require_login') && $user) {
            return $form->responses()->whereNotNull('submitted_at')
                ->where('respondent_type', $user->getMorphClass())->where('respondent_id', $user->getKey())->exists();
        }

        return request()->cookie($this->cookieName('done')) === '1';
    }

    private function ownsResponse(FormResponse $response): bool
    {
        if (! $this->form->setting('require_login')) {
            return true;
        }

        return $response->respondent_id === auth()->id() && $response->respondent_type === auth()->user()?->getMorphClass();
    }

    private function ensureResponse(): ?FormResponse
    {
        if ($this->preview) {
            return null;
        }

        if ($response = $this->response()) {
            return $response;
        }

        $form = $this->form;
        abort_unless($form->isAcceptingResponses(), 403);

        [$response, $token] = app(FormResponseService::class)->start($form, $form->setting('require_login') ? auth()->user() : null);
        $this->responseId = $response->getKey();
        Cookie::queue($this->cookieName('resume'), $token, 60 * 24 * 30);

        return $response;
    }

    public function begin(): void
    {
        if ($this->stage !== 'start') {
            return;
        }

        $this->ensureResponse();
        $this->stage = 'form';
    }

    /** Simpan otomatis tiap jawaban berubah. */
    public function updatedAnswers(mixed $value, string $key): void
    {
        if ($this->stage !== 'form') {
            return;
        }

        $questionId = (int) explode('.', $key)[0];
        $question = $this->question($questionId);
        $this->resetErrorBag('answers.'.$questionId);

        try {
            if ($this->preview) {
                app(FormAnswerValidator::class)->validate($question, $this->answers[$questionId] ?? null, final: false);

                return;
            }

            if (! RateLimiter::attempt('form-autosave:'.request()->ip(), 120, fn () => true, 60)) {
                return; // tetap di state; ikut terkirim saat "Kirim".
            }

            app(FormResponseService::class)->saveAnswer($this->ensureResponse(), $question, $this->answers[$questionId] ?? null);
        } catch (ValidationException $e) {
            $this->setErrorBag($this->getErrorBag()->merge($e->errors()));

            if (isset($e->errors()['form'])) {
                $this->expireIfNeeded();
            }
        }
    }

    public function updatedUploads(mixed $value, string $key): void
    {
        $questionId = (int) explode('.', $key)[0];
        $question = $this->question($questionId);
        $files = array_values(array_filter((array) ($this->uploads[$questionId] ?? [])));
        unset($this->uploads[$questionId]);
        $this->resetErrorBag(['uploads.'.$questionId, 'answers.'.$questionId]);

        if ($files === [] || $question->type !== QuestionType::FileUpload) {
            return;
        }

        $service = app(FormResponseService::class);

        try {
            if ($this->preview) {
                $max = (int) $question->option('max_files', 1);

                if (count($this->previewFiles[$questionId] ?? []) + count($files) > $max) {
                    throw ValidationException::withMessages(['uploads.'.$questionId => "Maksimal {$max} berkas."]);
                }

                foreach ($files as $file) {
                    $service->validateFile($question, $file);
                    $this->previewFiles[$questionId][] = $file->getClientOriginalName();
                }
            } else {
                $service->storeFiles($this->ensureResponse(), $question, $files);
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($this->getErrorBag()->merge($e->errors()));
        } finally {
            foreach ($files as $file) {
                $file->delete();
            }
        }
    }

    public function removeFile(int $questionId, int $index): void
    {
        if ($this->preview) {
            array_splice($this->previewFiles[$questionId], $index, 1);

            return;
        }

        if ($response = $this->response()) {
            app(FormResponseService::class)->removeFile($response, $this->question($questionId), $index);
        }
    }

    public function next(): void
    {
        $sections = $this->sections();

        try {
            $ids = array_column($sections[$this->page]['questions'] ?? [], 'id');
            app(FormResponseService::class)->validateAll(
                $this->allQuestions()->only($ids)->values(),
                $this->answers,
                $this->filesMap(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->page = min($this->page + 1, count($sections) - 1);
        $this->dispatch('form-page-changed');
    }

    public function back(): void
    {
        $this->page = max(0, $this->page - 1);
        $this->dispatch('form-page-changed');
    }

    public function submit(TurnstileVerifier $turnstile): void
    {
        if ($this->stage !== 'form') {
            return;
        }

        $service = app(FormResponseService::class);
        $form = $this->form;
        $response = $this->response();

        // Waktu kuis habis → langsung finalisasi dengan jawaban tersimpan.
        if ($response && $response->expires_at && now()->gt($response->expires_at->copy()->addSeconds(FormResponseService::GRACE_SECONDS))) {
            $this->finalize($response);

            return;
        }

        try {
            $service->validateAll($this->allQuestions()->values(), $this->answers, $this->filesMap());
        } catch (ValidationException $e) {
            $this->showErrors($e);

            return;
        }

        if ($this->preview) {
            $this->result = ['preview' => true, 'message' => $form->setting('confirmation_message')];
            $this->stage = 'done';

            return;
        }

        if ($this->needsTurnstile() && ! $turnstile->verify($this->turnstileToken, request()->ip())) {
            $this->turnstileToken = '';
            $this->addError('turnstile', 'Verifikasi keamanan gagal. Silakan centang ulang verifikasi.');
            $this->dispatch('form-turnstile-reset');

            return;
        }

        $key = 'form-submit:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::SUBMIT_PER_HOUR)) {
            $this->addError('form', 'Terlalu banyak pengiriman dari jaringan Anda. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.');

            return;
        }

        RateLimiter::hit($key, 3600);

        try {
            $response = $service->submit($this->ensureResponse(), $this->answers);
        } catch (ValidationException $e) {
            $this->showErrors($e);
            $this->dispatch('form-turnstile-reset');

            return;
        }

        $this->complete($response);
    }

    private function finalize(FormResponse $response): void
    {
        $this->complete(app(FormResponseService::class)->submit($response, []));
    }

    private function complete(FormResponse $response): void
    {
        Cookie::queue(Cookie::forget($this->cookieName('resume')));

        if ($this->form->setting('single_submission')) {
            Cookie::queue($this->cookieName('done'), '1', 60 * 24 * 365);
        }

        $this->result = $this->resultFor($response);
        $this->stage = 'done';
        $this->answers = [];
    }

    /** @return array<string, mixed> */
    private function resultFor(FormResponse $response): array
    {
        $form = $this->form;
        $result = ['preview' => false, 'message' => $form->setting('confirmation_message'), 'score' => null];
        $visibility = $form->scoreVisibility();

        $show = $form->is_quiz && match ($visibility) {
            ScoreVisibility::AfterSubmit => true,
            ScoreVisibility::AfterGrading => $response->graded_at !== null,
            default => false,
        };

        if ($show) {
            $pass = $form->setting('pass_score');
            $result['score'] = [
                'value' => $response->score,
                'max' => $response->max_score,
                'passed' => $pass === null ? null : $response->score >= (float) $pass,
            ];
        } elseif ($form->is_quiz && $visibility === ScoreVisibility::AfterGrading) {
            $result['pending_grading'] = true;
        }

        return $result;
    }

    private function expireIfNeeded(): void
    {
        $response = $this->response();

        if ($response && ! $response->isSubmitted() && $response->isExpired()) {
            $this->finalize($response);
        }
    }

    private function showErrors(ValidationException $e): void
    {
        $errors = $e->errors();
        $this->setErrorBag($errors);

        // Pindah ke halaman berisi kesalahan pertama.
        foreach ($this->sections() as $index => $section) {
            foreach ($section['questions'] as $q) {
                if (isset($errors['answers.'.$q['id']]) || isset($errors['uploads.'.$q['id']])) {
                    $this->page = $index;

                    return;
                }
            }
        }
    }

    public function needsTurnstile(): bool
    {
        return ! $this->preview && ! $this->form->setting('require_login') && $this->form->purpose !== FormPurpose::ExitTicket;
    }

    private function question(int $id): FormQuestion
    {
        $question = $this->allQuestions()->get($id);
        abort_unless($question, 404);

        return $question;
    }

    /** @return Collection<int, FormQuestion> */
    private function allQuestions(): Collection
    {
        return $this->form->questions()->get()->keyBy('id');
    }

    /** @return array<int, list<mixed>> */
    private function filesMap(): array
    {
        if ($this->preview) {
            return $this->previewFiles;
        }

        return $this->response()?->answers()->get()
            ->mapWithKeys(fn (FormAnswer $a) => [$a->form_question_id => $a->files ?? []])->all() ?? [];
    }

    /** @return list<array<string, mixed>> */
    private function sections(): array
    {
        return ParticipantFormView::sections($this->form, $this->response());
    }

    public function render(TurnstileVerifier $turnstile): View
    {
        $form = $this->form;
        $response = $this->response();
        $sections = $this->stage === 'form' ? $this->sections() : [];
        $this->page = min($this->page, max(0, count($sections) - 1));

        $files = $this->preview
            ? collect($this->previewFiles)->map(fn ($names) => array_map(fn ($n) => ['name' => $n], $names))->all()
            : collect($this->filesMap())->map(fn ($list) => array_map(fn ($f) => ['name' => $f['name']], $list))->all();

        return view('livewire.public-form', [
            'formTitle' => $form->title,
            'formDescription' => $form->description,
            'isQuiz' => $form->is_quiz,
            'timeLimit' => $form->timeLimitMinutes(),
            'sections' => $sections,
            'files' => $files,
            'secondsLeft' => $response?->expires_at ? max(0, now()->diffInSeconds($response->expires_at, false)) : null,
            'siteKey' => $turnstile->siteKey(),
            'needsTurnstile' => $this->needsTurnstile(),
            'loginUrl' => route('filament.admin.auth.login'),
            'opensAt' => $form->opens_at,
            'isFuture' => $form->opens_at && now()->lt($form->opens_at),
        ]);
    }
}
