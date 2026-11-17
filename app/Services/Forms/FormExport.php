<?php

namespace App\Services\Forms;

use App\Enums\QuestionType;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use Illuminate\Support\Collection;

/**
 * Export jawaban form (CSV/XLSX lewat SpreadsheetExport, aman formula injection).
 * Pertanyaan yang sudah dihapus tetapi punya jawaban tetap ikut (ditandai "(dihapus)").
 */
class FormExport
{
    /** @return Collection<int, FormQuestion> */
    public static function columns(Form $form): Collection
    {
        $answeredIds = FormAnswer::query()
            ->whereIn('form_response_id', $form->responses()->whereNotNull('submitted_at')->select('id'))
            ->distinct()->pluck('form_question_id');

        return FormQuestion::withTrashed()->where('form_id', $form->getKey())
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereIn('id', $answeredIds))
            ->orderBy('position')->get()
            ->filter(fn (FormQuestion $q) => $q->type->acceptsAnswer())->values();
    }

    /** @return list<list<string|int|float|null>> */
    public static function rows(Form $form): array
    {
        $columns = self::columns($form);
        $header = ['No', 'Waktu kirim', 'Email', 'Akun'];

        if ($form->is_quiz) {
            array_push($header, 'Nilai', 'Nilai maksimal', 'Pindah tab');
        }

        foreach ($columns as $q) {
            $header[] = $q->label.($q->trashed() ? ' (dihapus)' : '');
        }

        $rows = [$header];
        $responses = $form->responses()->whereNotNull('submitted_at')->with(['answers', 'respondent'])->orderBy('submitted_at')->get();

        foreach ($responses->values() as $i => $response) {
            /** @var FormResponse $response */
            $row = [$i + 1, $response->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'), $response->respondent_email, $response->respondent?->name];

            if ($form->is_quiz) {
                array_push($row, $response->score, $response->max_score, $response->tab_switches);
            }

            $answers = $response->answers->keyBy('form_question_id');

            foreach ($columns as $q) {
                $row[] = self::display($q, $answers->get($q->getKey()));
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** Teks jawaban untuk tabel/export. */
    public static function display(FormQuestion $question, ?FormAnswer $answer): string
    {
        if (! $answer) {
            return '';
        }

        if ($question->type === QuestionType::FileUpload) {
            return collect($answer->files ?? [])->pluck('name')->join(', ');
        }

        $value = $answer->value;

        if (is_array($value)) {
            $selected = array_map(fn ($s) => $s === FormAnswerValidator::OTHER ? 'Lainnya: '.($value['other'] ?? '') : $s, $value['selected'] ?? []);

            return implode(', ', $selected);
        }

        return $value === null ? '' : (string) $value;
    }
}
