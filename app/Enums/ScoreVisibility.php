<?php

namespace App\Enums;

/** Kapan nilai kuis terlihat oleh responden. */
enum ScoreVisibility: string
{
    case Never = 'never';
    case AfterSubmit = 'after_submit';
    case AfterGrading = 'after_grading';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Tidak pernah',
            self::AfterSubmit => 'Langsung setelah kirim',
            self::AfterGrading => 'Setelah semua soal dinilai',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $v) => [$v->value => $v->label()])->all();
    }
}
