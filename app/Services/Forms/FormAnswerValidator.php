<?php

namespace App\Services\Forms;

use App\Enums\QuestionType;
use App\Models\FormQuestion;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Validasi server SETIAP jawaban (docs/SPEC-FASE-11.md A2). Tidak pernah percaya data dari browser:
 * opsi harus salah satu pilihan sah, angka/tanggal/skala dalam rentang, pola NIM dari daftar aman.
 *
 * Bentuk nilai tersimpan (form_answers.value):
 * - pilihan ganda / dropdown / kotak centang: {"selected": [...], "other": "teks"|null}
 * - skala & angka: number; selain itu: string.
 */
class FormAnswerValidator
{
    /** Pola validasi yang boleh dipilih pembuat form (bukan regex bebas). */
    public const PATTERNS = [
        'nim' => ['/^[0-9A-Za-z.\-]{5,20}$/', 'Format NIM tidak valid.'],
        'digits' => ['/^[0-9]+$/', 'Hanya boleh angka.'],
        'letters' => ['/^[\pL\s.\'-]+$/u', 'Hanya boleh huruf.'],
    ];

    public const PATTERN_LABELS = [
        'nim' => 'NIM (huruf/angka, 5–20 karakter)',
        'digits' => 'Hanya angka',
        'letters' => 'Hanya huruf',
    ];

    public const OTHER = '__other__';

    /**
     * Normalisasi + validasi. $final=false (simpan otomatis) tidak memeriksa "wajib".
     *
     * @throws ValidationException
     */
    public function validate(FormQuestion $question, mixed $value, bool $final = true): mixed
    {
        $key = 'answers.'.$question->getKey();
        $type = $question->type;

        if (! $type->acceptsAnswer() || $type === QuestionType::FileUpload) {
            return null; // berkas divalidasi saat diunggah (FormResponseService::storeFiles)
        }

        $value = $this->normalize($type, $value);

        if ($this->isEmpty($value)) {
            if ($final && $question->is_required) {
                $this->fail($key, 'Pertanyaan ini wajib diisi.');
            }

            return null;
        }

        return match ($type) {
            QuestionType::ShortText, QuestionType::Paragraph => $this->text($question, $key, $value),
            QuestionType::MultipleChoice, QuestionType::Dropdown, QuestionType::Checkboxes => $this->choice($question, $key, $value),
            QuestionType::Number => $this->number($question, $key, $value),
            QuestionType::Email => filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 191
                ? strtolower($value) : $this->fail($key, 'Format email tidak valid.'),
            QuestionType::Phone => preg_match('/^(\+62|62|0)8[0-9]{7,12}$/', $value)
                ? $value : $this->fail($key, 'Nomor HP harus diawali 08, 628, atau +628 dan hanya berisi angka.'),
            QuestionType::Date => $this->date($question, $key, $value),
            QuestionType::Time => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $this->fail($key, 'Format waktu tidak valid (JJ:MM).'),
            QuestionType::LinearScale => $this->scale($question, $key, $value),
            default => null,
        };
    }

    private function normalize(QuestionType $type, mixed $value): mixed
    {
        if ($type->hasOptions()) {
            $selected = is_array($value) ? ($value['selected'] ?? $value) : ($value === null || $value === '' ? [] : [$value]);
            $selected = array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) $selected), fn ($v) => $v !== ''));
            $other = is_array($value) ? trim((string) ($value['other'] ?? '')) : '';

            return ['selected' => $selected, 'other' => $other !== '' ? $other : null];
        }

        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = trim((string) $value);

        return $type === QuestionType::Phone ? (preg_replace('/[\s\-().]/', '', $value) ?? $value) : $value;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return ($value['selected'] ?? []) === [];
        }

        return $value === '' || $value === null;
    }

    private function text(FormQuestion $q, string $key, string $value): string
    {
        $max = (int) $q->rule('max_length', $q->type === QuestionType::Paragraph ? 5000 : 500);
        $min = (int) $q->rule('min_length', 0);
        $length = mb_strlen($value);

        if ($length > min($max, 10000)) {
            $this->fail($key, "Maksimal {$max} karakter.");
        }

        if ($min > 0 && $length < $min) {
            $this->fail($key, "Minimal {$min} karakter.");
        }

        $pattern = self::PATTERNS[$q->rule('pattern')] ?? null;

        if ($pattern && ! preg_match($pattern[0], $value)) {
            $this->fail($key, $pattern[1]);
        }

        return $value;
    }

    /** @return array{selected: list<string>, other: ?string} */
    private function choice(FormQuestion $q, string $key, array $value): array
    {
        $choices = $q->choices();
        $selected = array_values(array_unique($value['selected']));
        $hasOther = in_array(self::OTHER, $selected, true);

        foreach ($selected as $item) {
            if ($item !== self::OTHER && ! in_array($item, $choices, true)) {
                $this->fail($key, 'Pilihan tidak valid.');
            }
        }

        if ($hasOther && (! $q->allowsOther() || blank($value['other']))) {
            $this->fail($key, $q->allowsOther() ? 'Isi jawaban "Lainnya".' : 'Pilihan tidak valid.');
        }

        if ($q->type !== QuestionType::Checkboxes && count($selected) > 1) {
            $this->fail($key, 'Pilih satu jawaban saja.');
        }

        if ($q->type === QuestionType::Checkboxes) {
            $min = (int) $q->rule('min_selected', 0);
            $max = (int) $q->rule('max_selected', 0);

            if ($min > 0 && count($selected) < $min) {
                $this->fail($key, "Pilih minimal {$min} jawaban.");
            }

            if ($max > 0 && count($selected) > $max) {
                $this->fail($key, "Pilih maksimal {$max} jawaban.");
            }
        }

        return ['selected' => $selected, 'other' => $hasOther ? mb_substr((string) $value['other'], 0, 500) : null];
    }

    private function number(FormQuestion $q, string $key, string $value): int|float
    {
        if (! is_numeric($value)) {
            $this->fail($key, 'Harus berupa angka.');
        }

        $number = $value + 0;
        $min = $q->rule('min');
        $max = $q->rule('max');

        if ($min !== null && $min !== '' && $number < (float) $min) {
            $this->fail($key, "Minimal {$min}.");
        }

        if ($max !== null && $max !== '' && $number > (float) $max) {
            $this->fail($key, "Maksimal {$max}.");
        }

        return $number;
    }

    private function date(FormQuestion $q, string $key, string $value): string
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m-d', $value) : false;

        if (! $date || $date->format('Y-m-d') !== $value) {
            $this->fail($key, 'Format tanggal tidak valid.');
        }

        return $value;
    }

    private function scale(FormQuestion $q, string $key, string $value): int
    {
        [$min, $max] = self::scaleRange($q);

        if (! ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
            $this->fail($key, "Pilih nilai {$min}–{$max}.");
        }

        return (int) $value;
    }

    /** @return array{0: int, 1: int} */
    public static function scaleRange(FormQuestion $q): array
    {
        $min = (int) $q->option('scale_min', 1) === 0 ? 0 : 1;
        $max = (int) $q->option('scale_max', 5);

        return [$min, in_array($max, [5, 10], true) ? $max : 5];
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
