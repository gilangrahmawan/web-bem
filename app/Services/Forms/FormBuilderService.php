<?php

namespace App\Services\Forms;

use App\Enums\FormStatus;
use App\Enums\QuestionType;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\FormSection;
use App\Models\User;
use App\Policies\FormPolicy;
use App\Services\ActivityLogger;
use App\Support\TrackingCode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pembuat form (docs/SPEC-FASE-11.md A4). Semua perubahan lewat sini: data dibersihkan,
 * dan perubahan yang merusak jawaban yang sudah masuk (hapus pertanyaan, ganti jenis, hapus/ubah opsi)
 * butuh konfirmasi eksplisit ($confirmed) bila form sudah punya jawaban terkirim.
 */
class FormBuilderService
{
    public const MAX_CHOICES = 100;

    public const FILE_KINDS = ['image', 'pdf', 'word', 'excel', 'powerpoint'];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly FormScoringService $scoring,
    ) {}

    /** @param  array{title: string, description?: ?string, division_id?: ?int, is_quiz?: bool}  $data */
    public function create(User $actor, array $data): Form
    {
        return DB::transaction(function () use ($actor, $data): Form {
            $form = new Form;
            $form->fill([
                'title' => mb_substr(trim($data['title']), 0, 200),
                'description' => $data['description'] ?? null,
                'is_quiz' => (bool) ($data['is_quiz'] ?? false),
                // Koordinator selalu membuat form untuk divisinya sendiri (keputusan pemilik).
                'division_id' => FormPolicy::managesAll($actor) ? ($data['division_id'] ?? null) : $actor->division_id,
            ]);
            $form->forceFill([
                'public_id' => self::newPublicId(),
                'status' => FormStatus::Draft,
                'settings' => Form::DEFAULT_SETTINGS,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $section = $form->sections()->create(['position' => 0]);
            $this->addQuestion($form, QuestionType::MultipleChoice, sectionId: $section->getKey());

            $this->logger->log('form.created', $form, ['title' => $form->title], $actor);

            return $form;
        });
    }

    public static function newPublicId(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $id = '';

            for ($c = 0; $c < 10; $c++) {
                $id .= TrackingCode::ALPHABET[random_int(0, strlen(TrackingCode::ALPHABET) - 1)];
            }

            if (! Form::withTrashed()->where('public_id', $id)->exists()) {
                return $id;
            }
        }

        throw new \RuntimeException('Gagal membuat ID form unik.');
    }

    public function duplicate(Form $source, User $actor): Form
    {
        return DB::transaction(function () use ($source, $actor): Form {
            $copy = $source->replicate(['public_id', 'status', 'closed_at', 'created_by', 'updated_by']);
            $copy->title = mb_substr('Salinan '.$source->title, 0, 200);
            $copy->division_id = FormPolicy::managesAll($actor) ? $source->division_id : $actor->division_id;
            $copy->forceFill([
                'public_id' => self::newPublicId(),
                'status' => FormStatus::Draft,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $sectionMap = [];

            foreach ($source->sections()->get() as $section) {
                $sectionMap[$section->getKey()] = $copy->sections()->create($section->only(['title', 'description', 'position']))->getKey();
            }

            foreach ($source->questions()->get() as $question) {
                $new = $question->replicate(['form_id', 'form_section_id']);
                $new->form_id = $copy->getKey();
                $new->form_section_id = $sectionMap[$question->form_section_id] ?? null;
                $new->save();
            }

            $this->logger->log('form.duplicated', $copy, ['source_id' => $source->getKey()], $actor);

            return $copy;
        });
    }

    /** Judul, deskripsi, jadwal, mode kuis & pengaturan. */
    public function updateDetails(Form $form, array $data, User $actor): Form
    {
        $settings = $form->allSettings();

        foreach (array_keys(Form::DEFAULT_SETTINGS) as $key) {
            if (array_key_exists($key, $data['settings'] ?? [])) {
                $settings[$key] = $data['settings'][$key];
            }
        }

        $form->fill(Arr::only($data, ['title', 'description', 'opens_at', 'closes_at', 'is_quiz']));

        if (array_key_exists('division_id', $data) && FormPolicy::managesAll($actor)) {
            $form->division_id = $data['division_id'];
        }

        $form->title = mb_substr(trim((string) $form->title), 0, 200) ?: 'Form tanpa judul';

        if ($form->opens_at && $form->closes_at && $form->closes_at->lte($form->opens_at)) {
            throw ValidationException::withMessages(['closes_at' => 'Waktu tutup harus setelah waktu buka.']);
        }

        $form->forceFill(['settings' => $this->cleanSettings($settings), 'updated_by' => $actor->getKey()])->save();

        return $form;
    }

    /** @return array<string, mixed> */
    private function cleanSettings(array $s): array
    {
        $retention = $s['retention_months'] ?? null;
        $limit = (int) ($s['time_limit_minutes'] ?? 0);
        $pass = $s['pass_score'] ?? null;

        return [
            'single_submission' => (bool) ($s['single_submission'] ?? true),
            'require_login' => (bool) ($s['require_login'] ?? false),
            'confirmation_message' => mb_substr(trim((string) ($s['confirmation_message'] ?? '')), 0, 1000) ?: Form::DEFAULT_SETTINGS['confirmation_message'],
            'shuffle_questions' => (bool) ($s['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($s['shuffle_options'] ?? false),
            'time_limit_minutes' => $limit > 0 ? min($limit, 600) : null,
            'pass_score' => $pass === null || $pass === '' ? null : max(0, (float) $pass),
            'score_visibility' => in_array($s['score_visibility'] ?? null, ['never', 'after_submit', 'after_grading'], true) ? $s['score_visibility'] : 'never',
            'retention_months' => $retention === null || $retention === '' || $retention === 'forever' ? null : max(1, min(120, (int) $retention)),
        ];
    }

    public function addSection(Form $form, ?int $afterPosition = null): FormSection
    {
        return DB::transaction(function () use ($form): FormSection {
            $position = (int) $form->sections()->max('position') + 1;

            return $form->sections()->create(['position' => $position, 'title' => 'Bagian tanpa judul']);
        });
    }

    public function updateSection(FormSection $section, array $data): FormSection
    {
        $section->update([
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 200) ?: null,
            'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 2000) ?: null,
        ]);

        return $section;
    }

    /** Hapus bagian: pertanyaannya pindah ke bagian sebelumnya (tidak merusak jawaban). Bagian pertama tidak bisa dihapus. */
    public function deleteSection(FormSection $section): void
    {
        DB::transaction(function () use ($section): void {
            $sections = $section->form->sections()->get();
            $index = $sections->search(fn ($s) => $s->is($section));

            if ($index === 0 || $index === false) {
                throw ValidationException::withMessages(['section' => 'Bagian pertama tidak bisa dihapus.']);
            }

            FormQuestion::query()->where('form_section_id', $section->getKey())
                ->update(['form_section_id' => $sections[$index - 1]->getKey()]);
            $section->delete();
        });
    }

    public function addQuestion(Form $form, QuestionType $type, ?int $afterQuestionId = null, ?int $sectionId = null): FormQuestion
    {
        return DB::transaction(function () use ($form, $type, $afterQuestionId, $sectionId): FormQuestion {
            $after = $afterQuestionId ? $form->questions()->whereKey($afterQuestionId)->first() : null;
            $sectionId = $after?->form_section_id ?? $sectionId ?? $form->sections()->value('id');
            $position = $after ? $after->position + 1 : (int) $form->questions()->max('position') + 1;

            $form->questions()->where('position', '>=', $position)->increment('position');

            return $form->questions()->create([
                'form_section_id' => $sectionId,
                'type' => $type,
                'label' => $type === QuestionType::TextBlock ? 'Judul' : 'Pertanyaan tanpa judul',
                'position' => $position,
                'options' => self::defaultOptions($type),
                'validation' => [],
                'points' => 0,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public static function defaultOptions(QuestionType $type): array
    {
        return match (true) {
            $type->hasOptions() => ['choices' => ['Opsi 1']],
            $type === QuestionType::LinearScale => ['scale_min' => 1, 'scale_max' => 5, 'min_label' => null, 'max_label' => null],
            $type === QuestionType::FileUpload => ['max_files' => 1, 'file_kinds' => self::FILE_KINDS],
            default => [],
        };
    }

    public function duplicateQuestion(FormQuestion $question): FormQuestion
    {
        return DB::transaction(function () use ($question): FormQuestion {
            $question->form->questions()->where('position', '>', $question->position)->increment('position');

            $copy = $question->replicate();
            $copy->position = $question->position + 1;
            $copy->save();

            return $copy;
        });
    }

    /**
     * Ubah pertanyaan. Data dibersihkan; perubahan merusak (ganti jenis / hapus atau ubah teks opsi)
     * pada form yang sudah punya jawaban terkirim → DestructiveChangeException kecuali $confirmed.
     */
    public function updateQuestion(FormQuestion $question, array $data, bool $confirmed = false): FormQuestion
    {
        $type = isset($data['type']) ? (QuestionType::tryFrom((string) $data['type']) ?? $question->type) : $question->type;
        $typeChanged = $type !== $question->type;
        $clean = $this->cleanQuestion($type, $data, $typeChanged ? null : $question);

        if (! $confirmed && $this->isDestructive($question, $type, $clean)) {
            throw new DestructiveChangeException;
        }

        $keyChanged = $clean['correct_answer'] != $question->correct_answer || $clean['points'] != $question->points;

        DB::transaction(function () use ($question, $clean, $keyChanged): void {
            $question->forceFill($clean)->save();

            // Kunci jawaban/poin berubah setelah ada jawaban → nilai otomatis dihitung ulang.
            if ($keyChanged && $question->form->is_quiz) {
                $this->regrade($question);
            }
        });

        return $question;
    }

    private function isDestructive(FormQuestion $question, QuestionType $type, array $clean): bool
    {
        if (! $question->form->hasSubmittedResponses()) {
            return false;
        }

        if ($type !== $question->type) {
            return true;
        }

        if ($type->hasOptions()) {
            return array_diff($question->choices(), $clean['options']['choices'] ?? []) !== [];
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function cleanQuestion(QuestionType $type, array $data, ?FormQuestion $current): array
    {
        $options = $data['options'] ?? ($current?->options ?? self::defaultOptions($type));
        $validation = $data['validation'] ?? ($current?->validation ?? []);
        $key = $data['correct_answer'] ?? ($current?->correct_answer ?? []);
        $points = (int) ($data['points'] ?? $current?->points ?? 0);

        $cleanOptions = [];
        $cleanValidation = [];
        $cleanKey = null;

        if ($type->hasOptions()) {
            $choices = [];

            foreach ((array) ($options['choices'] ?? []) as $choice) {
                $choice = mb_substr(trim((string) $choice), 0, 200);

                if ($choice !== '' && $choice !== FormAnswerValidator::OTHER && ! in_array($choice, $choices, true)) {
                    $choices[] = $choice;
                }
            }

            $cleanOptions['choices'] = array_slice($choices, 0, self::MAX_CHOICES);

            if ($type->allowsOther()) {
                $cleanOptions['allow_other'] = (bool) ($options['allow_other'] ?? false);
            }

            if ($type === QuestionType::Checkboxes) {
                $cleanOptions['partial_credit'] = (bool) ($options['partial_credit'] ?? false);
                $cleanValidation = array_filter([
                    'min_selected' => max(0, (int) ($validation['min_selected'] ?? 0)) ?: null,
                    'max_selected' => max(0, (int) ($validation['max_selected'] ?? 0)) ?: null,
                ]);
            }

            $cleanKey = ['choices' => array_values(array_intersect((array) ($key['choices'] ?? []), $cleanOptions['choices']))];

            if ($type !== QuestionType::Checkboxes) {
                $cleanKey['choices'] = array_slice($cleanKey['choices'], 0, 1);
            }
        } elseif ($type === QuestionType::LinearScale) {
            $cleanOptions = [
                'scale_min' => (int) ($options['scale_min'] ?? 1) === 0 ? 0 : 1,
                'scale_max' => in_array((int) ($options['scale_max'] ?? 5), [5, 10], true) ? (int) $options['scale_max'] : 5,
                'min_label' => mb_substr(trim((string) ($options['min_label'] ?? '')), 0, 50) ?: null,
                'max_label' => mb_substr(trim((string) ($options['max_label'] ?? '')), 0, 50) ?: null,
            ];
        } elseif ($type === QuestionType::FileUpload) {
            $kinds = array_values(array_intersect(self::FILE_KINDS, (array) ($options['file_kinds'] ?? self::FILE_KINDS)));
            $cleanOptions = [
                'max_files' => max(1, min(3, (int) ($options['max_files'] ?? 1))),
                'file_kinds' => $kinds ?: self::FILE_KINDS,
            ];
        } elseif (in_array($type, [QuestionType::ShortText, QuestionType::Paragraph], true)) {
            $cleanValidation = array_filter([
                'min_length' => max(0, (int) ($validation['min_length'] ?? 0)) ?: null,
                'max_length' => max(0, min(10000, (int) ($validation['max_length'] ?? 0))) ?: null,
                'pattern' => $type === QuestionType::ShortText && array_key_exists($validation['pattern'] ?? '', FormAnswerValidator::PATTERNS) ? $validation['pattern'] : null,
            ]);

            if ($type === QuestionType::ShortText) {
                $texts = array_values(array_filter(array_map(fn ($t) => mb_substr(trim((string) $t), 0, 200), (array) ($key['texts'] ?? [])), fn ($t) => $t !== ''));
                $cleanKey = ['texts' => array_slice(array_unique($texts), 0, 20)];
            }
        } elseif ($type === QuestionType::Number) {
            $cleanValidation = array_filter([
                'min' => is_numeric($validation['min'] ?? null) ? $validation['min'] + 0 : null,
                'max' => is_numeric($validation['max'] ?? null) ? $validation['max'] + 0 : null,
            ], fn ($v) => $v !== null);
        }

        $gradable = $type->isAutoGradable() || $type->isManuallyGraded();

        return [
            'type' => $type,
            'label' => mb_substr(trim((string) ($data['label'] ?? $current?->label ?? '')), 0, 500),
            'help_text' => mb_substr(trim((string) ($data['help_text'] ?? $current?->help_text ?? '')), 0, 2000) ?: null,
            'is_required' => $type->acceptsAnswer() && (bool) ($data['is_required'] ?? $current?->is_required ?? false),
            'options' => $cleanOptions,
            'validation' => $cleanValidation,
            'points' => $gradable ? max(0, min(1000, $points)) : 0,
            'correct_answer' => $type->isAutoGradable() ? $cleanKey : null,
        ];
    }

    public function deleteQuestion(FormQuestion $question, bool $confirmed = false): void
    {
        if (! $confirmed && $question->form->hasSubmittedResponses()) {
            throw new DestructiveChangeException;
        }

        DB::transaction(function () use ($question): void {
            $form = $question->form;
            // Soft delete: jawaban lama tetap terbaca di export; pertanyaan hilang dari form.
            $question->delete();

            if ($form->is_quiz) {
                $form->responses()->whereNotNull('submitted_at')->get()->each(fn (FormResponse $r) => $this->scoring->recalculate($r));
            }
        });
    }

    /**
     * Urutan campuran bagian & pertanyaan dari drag & drop: ["s:3", "q:10", "q:11", "s:4", "q:12"].
     * Pertanyaan ikut bagian terakhir di atasnya. Bagian pertama selalu tetap di atas.
     *
     * @param  list<string>  $items
     */
    public function reorder(Form $form, array $items): void
    {
        DB::transaction(function () use ($form, $items): void {
            $sections = $form->sections()->pluck('id')->all();
            $questions = $form->questions()->pluck('id')->all();
            $currentSection = $sections[0] ?? null;
            $sectionPos = 1;
            $questionPos = 0;
            $seenSections = [$currentSection];

            foreach ($items as $item) {
                [$kind, $id] = array_pad(explode(':', (string) $item, 2), 2, null);
                $id = (int) $id;

                if ($kind === 's' && in_array($id, $sections, true) && $id !== $sections[0]) {
                    FormSection::query()->whereKey($id)->update(['position' => $sectionPos++]);
                    $currentSection = $id;
                    $seenSections[] = $id;
                } elseif ($kind === 'q' && in_array($id, $questions, true)) {
                    FormQuestion::query()->whereKey($id)->update(['position' => $questionPos++, 'form_section_id' => $currentSection]);
                }
            }
        });
    }

    public function publish(Form $form, User $actor): void
    {
        $questions = $form->questions()->get();
        $answerable = $questions->filter(fn (FormQuestion $q) => $q->type->acceptsAnswer());
        $errors = [];

        if ($answerable->isEmpty()) {
            $errors[] = 'Tambahkan minimal satu pertanyaan.';
        }

        foreach ($questions as $i => $q) {
            $no = $i + 1;

            if (blank($q->label)) {
                $errors[] = "Pertanyaan nomor {$no} belum punya judul.";
            }

            if ($q->type->hasOptions() && $q->choices() === []) {
                $errors[] = "Pertanyaan nomor {$no} belum punya opsi.";
            }

            if ($form->is_quiz && $q->points > 0 && $q->type->isAutoGradable() && blank($q->correct_answer['choices'] ?? $q->correct_answer['texts'] ?? null)) {
                $errors[] = "Pertanyaan nomor {$no} bernilai poin tetapi belum punya kunci jawaban.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['publish' => $errors]);
        }

        $form->forceFill(['status' => FormStatus::Published, 'closed_at' => null, 'updated_by' => $actor->getKey()])->save();
        $this->logger->log('form.published', $form, ['title' => $form->title], $actor);
    }

    public function close(Form $form, User $actor): void
    {
        $form->forceFill(['status' => FormStatus::Closed, 'closed_at' => now(), 'updated_by' => $actor->getKey()])->save();
        $this->logger->log('form.closed', $form, ['title' => $form->title], $actor);
    }

    public function delete(Form $form, User $actor): void
    {
        DB::transaction(function () use ($form, $actor): void {
            // Hanya form tanpa jawaban terkirim (FormPolicy::delete); draf jawaban ikut dibuang.
            $form->responses()->get()->each(fn (FormResponse $r) => app(FormResponseService::class)->purge($r));
            $form->delete();
            $this->logger->log('form.deleted', $form, ['title' => $form->title], $actor);
        });
    }

    private function regrade(FormQuestion $question): void
    {
        $responses = $question->form->responses()->whereNotNull('submitted_at')->with('answers')->get();

        foreach ($responses as $response) {
            $answer = $response->answers->firstWhere('form_question_id', $question->getKey());

            if ($answer && $question->type->isAutoGradable()) {
                $answer->forceFill(['points_awarded' => $this->scoring->autoScore($question, $answer->value)])->save();
            }

            $this->scoring->recalculate($response);
        }
    }
}
