<?php

namespace App\Services\Forms;

use App\Enums\QuestionType;
use App\Models\FormQuestion;
use App\Models\FormResponse;

/**
 * Penilaian kuis (docs/SPEC-FASE-11.md A3):
 * - pilihan ganda/dropdown: benar = poin penuh.
 * - kotak centang: semua tepat = poin penuh; bila nilai parsial aktif:
 *   (benar dipilih − salah dipilih) ÷ jumlah jawaban benar × poin, minimal 0 (keputusan pemilik).
 * - jawaban singkat: cocok dengan salah satu jawaban benar (abaikan huruf besar/kecil & spasi).
 * - paragraf/upload: manual (null sampai dinilai).
 */
class FormScoringService
{
    /** Nilai otomatis satu jawaban; null = perlu penilaian manual / tidak dinilai. */
    public function autoScore(FormQuestion $question, mixed $value): ?float
    {
        $points = (float) $question->points;
        $key = $question->correct_answer ?? [];

        if ($points <= 0 || ! $question->type->isAutoGradable()) {
            return null;
        }

        if ($value === null) {
            return 0.0;
        }

        return match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::Dropdown => in_array($value['selected'][0] ?? null, $key['choices'] ?? [], true) ? $points : 0.0,
            QuestionType::Checkboxes => $this->checkboxes($question, $value, $key, $points),
            QuestionType::ShortText => $this->shortText((string) $value, $key, $points),
            default => null,
        };
    }

    private function checkboxes(FormQuestion $q, array $value, array $key, float $points): float
    {
        $correct = array_values(array_unique($key['choices'] ?? []));
        $selected = array_values(array_unique(array_filter($value['selected'] ?? [], fn ($s) => $s !== FormAnswerValidator::OTHER)));

        if ($correct === []) {
            return 0.0;
        }

        if (! ($q->options['partial_credit'] ?? false)) {
            sort($correct);
            sort($selected);

            return $correct === $selected ? $points : 0.0;
        }

        $right = count(array_intersect($selected, $correct));
        $wrong = count(array_diff($selected, $correct));

        return round(max(0, ($right - $wrong) / count($correct)) * $points, 2);
    }

    private function shortText(string $value, array $key, float $points): float
    {
        $normalize = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? $s));
        $answers = array_map(fn ($a) => $normalize((string) $a), $key['texts'] ?? []);

        return in_array($normalize($value), $answers, true) ? $points : 0.0;
    }

    /** Hitung ulang total nilai respons; graded_at diisi bila tidak ada lagi jawaban manual yang belum dinilai. */
    public function recalculate(FormResponse $response): void
    {
        $form = $response->form;

        if (! $form->is_quiz) {
            return;
        }

        $questions = $form->questions()->get()->filter(fn (FormQuestion $q) => $q->type->acceptsAnswer() && $q->points > 0);
        $answers = $response->answers()->get()->keyBy('form_question_id');

        $score = 0.0;
        $pending = false;

        foreach ($questions as $question) {
            $answer = $answers->get($question->getKey());

            if ($question->type->isManuallyGraded()) {
                $hasContent = $answer && (filled($answer->value) || filled($answer->files));

                if ($hasContent && $answer->points_awarded === null) {
                    $pending = true;
                }
            }

            $score += (float) ($answer?->points_awarded ?? 0);
        }

        $response->forceFill([
            'score' => round($score, 2),
            'max_score' => (float) $questions->sum('points'),
            'graded_at' => $pending ? null : ($response->graded_at ?? now()),
        ])->save();
    }
}
