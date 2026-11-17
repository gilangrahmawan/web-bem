<?php

namespace App\Services\Forms;

use App\Enums\DriveFileKind;
use App\Enums\QuestionType;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Siklus jawaban (docs/SPEC-FASE-11.md A3): mulai → simpan otomatis → kirim (terkunci) → nilai.
 * Batas waktu kuis dihitung di SERVER (expires_at); jawaban setelah batas ditolak dan respons
 * difinalisasi dengan jawaban yang sudah tersimpan. Berkas di disk privat. Tanpa IP.
 */
class FormResponseService
{
    public const IMAGE_MAX_KB = 2048;

    public const DOCUMENT_MAX_KB = 10240;

    /** Toleransi jaringan saat kirim di detik terakhir kuis. */
    public const GRACE_SECONDS = 30;

    public function __construct(
        private readonly FormAnswerValidator $validator,
        private readonly FormScoringService $scoring,
        private readonly ImageService $images,
    ) {}

    /**
     * Mulai respons: urutan pertanyaan/opsi diacak per peserta (bila diatur) dan batas waktu dicatat.
     *
     * @return array{0: FormResponse, 1: string} respons + token resume mentah (untuk cookie)
     */
    public function start(Form $form, ?User $user = null): array
    {
        $token = Str::random(40);

        $response = new FormResponse(['form_id' => $form->getKey()]);
        $response->forceFill([
            'resume_token' => hash('sha256', $token),
            'question_order' => $this->buildOrder($form),
            'started_at' => now(),
            'expires_at' => $form->timeLimitMinutes() ? now()->addMinutes($form->timeLimitMinutes()) : null,
        ]);

        if ($user) {
            $response->respondent()->associate($user);
        }

        $response->save();

        return [$response, $token];
    }

    /** @return array{questions: list<int>, options: array<int, list<string>>} */
    private function buildOrder(Form $form): array
    {
        $questions = $form->questions()->get();
        $sections = $form->sections()->pluck('id')->all();
        $order = [];
        $options = [];

        foreach ($sections as $i => $sectionId) {
            $inSection = $questions->filter(fn (FormQuestion $q) => $q->form_section_id === $sectionId || ($i === 0 && $q->form_section_id === null));

            if ($form->setting('shuffle_questions')) {
                // Teks/judul tetap di tempatnya; hanya pertanyaan yang diacak.
                $shuffled = $inSection->filter(fn ($q) => $q->type->acceptsAnswer())->shuffle()->values()->all();
                $slots = [];

                foreach ($inSection as $q) {
                    $slots[] = $q->type->acceptsAnswer() ? array_shift($shuffled) : $q;
                }

                $inSection = collect($slots);
            }

            foreach ($inSection as $q) {
                $order[] = $q->getKey();

                if ($form->setting('shuffle_options') && $q->type->hasOptions()) {
                    $options[$q->getKey()] = collect($q->choices())->shuffle()->values()->all();
                }
            }
        }

        return ['questions' => $order, 'options' => $options];
    }

    public function findByToken(Form $form, ?string $token): ?FormResponse
    {
        if (blank($token)) {
            return null;
        }

        return $form->responses()->where('resume_token', hash('sha256', $token))->first();
    }

    /** Simpan otomatis satu jawaban (tanpa cek "wajib"). Ditolak bila sudah dikirim atau waktu habis. */
    public function saveAnswer(FormResponse $response, FormQuestion $question, mixed $value): void
    {
        $this->ensureWritable($response);
        $this->ensureQuestionBelongs($response, $question);

        if ($question->type === QuestionType::FileUpload || ! $question->type->acceptsAnswer()) {
            return;
        }

        $clean = $this->validator->validate($question, $value, final: false);

        FormAnswer::query()->updateOrCreate(
            ['form_response_id' => $response->getKey(), 'form_question_id' => $question->getKey()],
            ['value' => $clean],
        );
    }

    /**
     * Validasi satu berkas untuk pertanyaan upload (dipakai juga saat pratinjau, tanpa menyimpan).
     *
     * @throws ValidationException
     */
    public function validateFile(FormQuestion $question, UploadedFile $file): DriveFileKind
    {
        $key = 'uploads.'.$question->getKey();
        $kinds = array_map(fn ($k) => DriveFileKind::from($k), $question->option('file_kinds', FormBuilderService::FILE_KINDS));
        $kind = DriveFileKind::fromExtension($file->getClientOriginalExtension());

        if (! $kind || ! in_array($kind, $kinds, true) || ! in_array($file->getMimeType(), $kind->mimeTypes(), true)) {
            throw ValidationException::withMessages([$key => 'Jenis berkas tidak diizinkan. Yang diterima: '
                .collect($kinds)->map->label()->join(', ').'.']);
        }

        $maxKb = $kind === DriveFileKind::Image ? self::IMAGE_MAX_KB : self::DOCUMENT_MAX_KB;

        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([$key => 'Ukuran berkas maksimal '.($maxKb / 1024).' MB.']);
        }

        return $kind;
    }

    /** @param  list<UploadedFile>  $files */
    public function storeFiles(FormResponse $response, FormQuestion $question, array $files): FormAnswer
    {
        $this->ensureWritable($response);
        $this->ensureQuestionBelongs($response, $question);

        if ($question->type !== QuestionType::FileUpload) {
            throw ValidationException::withMessages(['uploads.'.$question->getKey() => 'Pertanyaan ini bukan upload berkas.']);
        }

        $answer = FormAnswer::query()->firstOrNew(['form_response_id' => $response->getKey(), 'form_question_id' => $question->getKey()]);
        $existing = $answer->files ?? [];
        $max = (int) $question->option('max_files', 1);

        if (count($existing) + count($files) > $max) {
            throw ValidationException::withMessages(['uploads.'.$question->getKey() => "Maksimal {$max} berkas."]);
        }

        $kinds = array_map(fn (UploadedFile $f) => $this->validateFile($question, $f), $files);
        $directory = FormAnswer::DIRECTORY.'/'.$response->form_id.'/'.$response->getKey();

        foreach ($files as $i => $file) {
            $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            if ($kinds[$i] === DriveFileKind::Image) {
                $path = $this->images->storeWebp($file, $directory, 1600, FormAnswer::DISK);
                $name = mb_substr($base, 0, 150).'.webp';
                $mime = 'image/webp';
            } else {
                $extension = strtolower($file->getClientOriginalExtension());
                $path = $file->storeAs($directory, Str::uuid().'.'.$extension, FormAnswer::DISK);
                $name = mb_substr($base, 0, 150).'.'.$extension;
                $mime = $file->getMimeType();
            }

            $existing[] = ['path' => $path, 'name' => $name, 'mime' => $mime, 'size' => Storage::disk(FormAnswer::DISK)->size($path)];
        }

        $answer->files = $existing;
        $answer->save();

        return $answer;
    }

    public function removeFile(FormResponse $response, FormQuestion $question, int $index): void
    {
        $this->ensureWritable($response);

        $answer = $response->answers()->where('form_question_id', $question->getKey())->first();
        $files = $answer?->files ?? [];

        if (! isset($files[$index])) {
            return;
        }

        Storage::disk(FormAnswer::DISK)->delete($files[$index]['path']);
        array_splice($files, $index, 1);
        $answer->files = $files;
        $answer->save();
    }

    /**
     * Kirim jawaban (final, terkunci). $answers = [question_id => nilai] dari peserta.
     * Bila waktu kuis sudah habis: jawaban baru diabaikan, respons difinalisasi dengan yang tersimpan.
     */
    public function submit(FormResponse $response, array $answers): FormResponse
    {
        return DB::transaction(function () use ($response, $answers): FormResponse {
            /** @var FormResponse $response */
            $response = FormResponse::query()->whereKey($response->getKey())->lockForUpdate()->firstOrFail();
            $form = $response->form;

            if ($response->isSubmitted()) {
                throw ValidationException::withMessages(['form' => 'Jawaban sudah dikirim dan tidak bisa diubah.']);
            }

            $expired = $response->expires_at && now()->gt($response->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
            $questions = $this->questionsFor($response);
            $stored = $response->answers()->get()->keyBy('form_question_id');

            if (! $expired) {
                $clean = $this->validateAll($questions, $answers, $stored->map(fn (FormAnswer $a) => $a->files ?? [])->all());

                foreach ($clean as $questionId => $value) {
                    FormAnswer::query()->updateOrCreate(
                        ['form_response_id' => $response->getKey(), 'form_question_id' => $questionId],
                        ['value' => $value],
                    );
                }

                $stored = $response->answers()->get()->keyBy('form_question_id');
            }

            $this->assertSingleSubmission($form, $response, $questions, $stored);

            foreach ($questions as $question) {
                $answer = $stored->get($question->getKey());

                if ($form->is_quiz && $answer && $question->type->isAutoGradable()) {
                    $answer->forceFill(['points_awarded' => $this->scoring->autoScore($question, $answer->value)])->save();
                }
            }

            $response->forceFill(['submitted_at' => now()])->save();
            $this->scoring->recalculate($response);

            return $response->refresh();
        });
    }

    /**
     * Validasi final semua jawaban (juga untuk pratinjau, tanpa menyimpan).
     *
     * @param  Collection<int, FormQuestion>  $questions
     * @param  array<int, list<mixed>>  $files  [question_id => daftar berkas]
     * @return array<int, mixed> nilai bersih per pertanyaan
     *
     * @throws ValidationException
     */
    public function validateAll(Collection $questions, array $answers, array $files = []): array
    {
        $clean = [];
        $errors = [];

        foreach ($questions as $question) {
            if (! $question->type->acceptsAnswer()) {
                continue;
            }

            if ($question->type === QuestionType::FileUpload) {
                if ($question->is_required && empty($files[$question->getKey()])) {
                    $errors['answers.'.$question->getKey()] = 'Pertanyaan ini wajib diisi.';
                }

                continue;
            }

            try {
                $clean[$question->getKey()] = $this->validator->validate($question, $answers[$question->getKey()] ?? null);
            } catch (ValidationException $e) {
                $errors += $e->errors();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /** Pertanyaan respons ini, mengikuti urutan acak yang dicatat saat mulai. @return Collection<int, FormQuestion> */
    public function questionsFor(FormResponse $response): Collection
    {
        $questions = $response->form->questions()->get()->keyBy('id');
        $order = $response->question_order['questions'] ?? [];

        $ordered = collect($order)->map(fn ($id) => $questions->get($id))->filter();

        // Pertanyaan yang ditambahkan setelah respons dimulai ikut di akhir.
        return $ordered->concat($questions->except($order)->values())->values();
    }

    /** Satu kali isi: per akun bila wajib login; tanpa login per email (bila ada pertanyaan email wajib). */
    private function assertSingleSubmission(Form $form, FormResponse $response, Collection $questions, Collection $stored): void
    {
        $emailQuestion = $questions->first(fn (FormQuestion $q) => $q->type === QuestionType::Email && $q->is_required);
        $email = $emailQuestion ? $stored->get($emailQuestion->getKey())?->value : null;

        $response->respondent_email = is_string($email) ? mb_strtolower($email) : null;

        if (! $form->setting('single_submission')) {
            return;
        }

        $others = $form->responses()->whereNotNull('submitted_at')->whereKeyNot($response->getKey());

        if ($response->respondent_id && (clone $others)->where('respondent_type', $response->respondent_type)->where('respondent_id', $response->respondent_id)->exists()) {
            throw ValidationException::withMessages(['form' => 'Anda sudah pernah mengisi form ini.']);
        }

        if ($response->respondent_email && (clone $others)->where('respondent_email', $response->respondent_email)->exists()) {
            throw ValidationException::withMessages(['answers.'.$emailQuestion->getKey() => 'Email ini sudah pernah mengirim jawaban untuk form ini.']);
        }
    }

    /** Nilai manual esai/upload. Poin dibatasi 0..poin pertanyaan. */
    public function grade(FormAnswer $answer, float $points, ?string $note, User $grader): void
    {
        DB::transaction(function () use ($answer, $points, $note, $grader): void {
            $max = (float) $answer->question->points;
            $answer->forceFill([
                'points_awarded' => round(max(0, min($max, $points)), 2),
                'grader_note' => $note !== null ? mb_substr(trim($note), 0, 2000) : null,
            ])->save();

            $response = $answer->response;
            $response->forceFill(['graded_by' => $grader->getKey()])->save();
            $this->scoring->recalculate($response);
        });
    }

    /** Hapus respons beserta berkasnya (retensi / hapus form). */
    public function purge(FormResponse $response): void
    {
        foreach ($response->answers()->get() as $answer) {
            foreach ($answer->files ?? [] as $file) {
                Storage::disk(FormAnswer::DISK)->delete($file['path']);
            }
        }

        $response->delete();
    }

    /**
     * Retensi (dijadwalkan harian): jawaban dihapus N bulan setelah form ditutup (pengaturan per form,
     * bawaan 12 bulan, null = simpan selamanya). Draf yang tidak pernah dikirim dibuang setelah 30 hari.
     *
     * @return array{responses: int, drafts: int}
     */
    public function purgeExpired(): array
    {
        $responses = 0;

        Form::withTrashed()
            ->where(fn ($q) => $q->whereNotNull('closed_at')->orWhere('closes_at', '<', now()))
            ->whereHas('responses')
            ->each(function (Form $form) use (&$responses): void {
                $months = $form->setting('retention_months');
                $closedAt = $form->closed_at ?? $form->closes_at;

                if ($months === null || $closedAt === null || $closedAt->copy()->addMonths((int) $months)->isFuture()) {
                    return;
                }

                $form->responses()->each(function (FormResponse $response) use (&$responses): void {
                    $this->purge($response);
                    $responses++;
                });
            });

        $drafts = 0;

        FormResponse::query()->whereNull('submitted_at')->where('created_at', '<', now()->subDays(30))
            ->each(function (FormResponse $response) use (&$drafts): void {
                $this->purge($response);
                $drafts++;
            });

        return ['responses' => $responses, 'drafts' => $drafts];
    }

    private function ensureWritable(FormResponse $response): void
    {
        if ($response->isSubmitted()) {
            throw ValidationException::withMessages(['form' => 'Jawaban sudah dikirim dan tidak bisa diubah.']);
        }

        if ($response->isExpired()) {
            throw ValidationException::withMessages(['form' => 'Waktu pengerjaan sudah habis.']);
        }
    }

    private function ensureQuestionBelongs(FormResponse $response, FormQuestion $question): void
    {
        abort_unless($question->form_id === $response->form_id, 404);
    }
}
