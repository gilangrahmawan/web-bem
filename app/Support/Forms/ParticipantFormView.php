<?php

namespace App\Support\Forms;

use App\Enums\DriveFileKind;
use App\Enums\QuestionType;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Services\Forms\FormAnswerValidator;
use App\Services\Forms\FormBuilderService;
use App\Services\Forms\FormResponseService;

/**
 * Tampilan form untuk peserta: HANYA field aman. Kunci jawaban (correct_answer), poin per opsi,
 * dan data internal lain tidak pernah dimasukkan (docs/SPEC-FASE-11.md A3).
 */
final class ParticipantFormView
{
    /**
     * @return list<array{id: int, title: ?string, description: ?string, questions: list<array<string, mixed>>}>
     */
    public static function sections(Form $form, ?FormResponse $response = null): array
    {
        $questions = $response
            ? app(FormResponseService::class)->questionsFor($response)
            : $form->questions()->get();
        $optionOrder = $response?->question_order['options'] ?? [];
        $sections = $form->sections()->get();
        $firstId = $sections->first()?->getKey();

        $result = $sections->mapWithKeys(fn ($s) => [$s->getKey() => [
            'id' => $s->getKey(),
            'title' => $s->title,
            'description' => $s->description,
            'questions' => [],
        ]])->all();

        foreach ($questions as $q) {
            $sectionId = isset($result[$q->form_section_id]) ? $q->form_section_id : $firstId;

            if ($sectionId === null) {
                continue;
            }

            $result[$sectionId]['questions'][] = self::question($q, $optionOrder[$q->getKey()] ?? null, $form->is_quiz);
        }

        // Bagian kosong tidak ditampilkan sebagai halaman.
        return array_values(array_filter($result, fn ($s) => $s['questions'] !== []));
    }

    /** @return array<string, mixed> */
    private static function question(FormQuestion $q, ?array $optionOrder, bool $quiz): array
    {
        $choices = $q->choices();

        if ($optionOrder !== null) {
            // Urutan acak tercatat; opsi yang ditambah belakangan ikut di akhir.
            $choices = array_values(array_unique([...array_intersect($optionOrder, $choices), ...$choices]));
        }

        $data = [
            'id' => $q->getKey(),
            'type' => $q->type->value,
            'label' => $q->label,
            'help' => $q->help_text,
            'required' => $q->is_required,
            'points' => $quiz && $q->type->acceptsAnswer() && $q->points > 0 ? $q->points : null,
        ];

        if ($q->type->hasOptions()) {
            $data['choices'] = $choices;
            $data['allow_other'] = $q->allowsOther();
        }

        if ($q->type === QuestionType::LinearScale) {
            [$min, $max] = FormAnswerValidator::scaleRange($q);
            $data += ['min' => $min, 'max' => $max, 'min_label' => $q->option('min_label'), 'max_label' => $q->option('max_label')];
        }

        if ($q->type === QuestionType::FileUpload) {
            $kinds = array_map(fn ($k) => DriveFileKind::from($k), $q->option('file_kinds', FormBuilderService::FILE_KINDS));
            $data['max_files'] = (int) $q->option('max_files', 1);
            $data['accept'] = collect($kinds)->flatMap(fn (DriveFileKind $k) => array_map(fn ($e) => '.'.$e, $k->extensions()))->join(',');
            $data['kinds_label'] = collect($kinds)->map->label()->join(', ');
        }

        if ($q->type === QuestionType::Number) {
            $data['min'] = $q->rule('min');
            $data['max'] = $q->rule('max');
        }

        if (in_array($q->type, [QuestionType::ShortText, QuestionType::Paragraph], true)) {
            $data['max_length'] = $q->rule('max_length', $q->type === QuestionType::Paragraph ? 5000 : 500);
        }

        return $data;
    }
}
