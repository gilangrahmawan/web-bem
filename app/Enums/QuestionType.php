<?php

namespace App\Enums;

/** 13 jenis pertanyaan mesin form (docs/SPEC-FASE-11.md A2). */
enum QuestionType: string
{
    case ShortText = 'short_text';
    case Paragraph = 'paragraph';
    case MultipleChoice = 'multiple_choice';
    case Checkboxes = 'checkboxes';
    case Dropdown = 'dropdown';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
    case Date = 'date';
    case Time = 'time';
    case LinearScale = 'linear_scale';
    case FileUpload = 'file_upload';
    case TextBlock = 'text_block'; // teks/judul pemisah, bukan pertanyaan

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Jawaban singkat',
            self::Paragraph => 'Paragraf',
            self::MultipleChoice => 'Pilihan ganda',
            self::Checkboxes => 'Kotak centang',
            self::Dropdown => 'Dropdown',
            self::Number => 'Angka',
            self::Email => 'Email',
            self::Phone => 'Nomor HP',
            self::Date => 'Tanggal',
            self::Time => 'Waktu',
            self::LinearScale => 'Skala linear',
            self::FileUpload => 'Upload file',
            self::TextBlock => 'Teks / judul',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ShortText => 'heroicon-o-minus',
            self::Paragraph => 'heroicon-o-bars-3-bottom-left',
            self::MultipleChoice => 'heroicon-o-stop-circle',
            self::Checkboxes => 'heroicon-o-check-circle',
            self::Dropdown => 'heroicon-o-chevron-down',
            self::Number => 'heroicon-o-hashtag',
            self::Email => 'heroicon-o-at-symbol',
            self::Phone => 'heroicon-o-phone',
            self::Date => 'heroicon-o-calendar',
            self::Time => 'heroicon-o-clock',
            self::LinearScale => 'heroicon-o-adjustments-horizontal',
            self::FileUpload => 'heroicon-o-arrow-up-tray',
            self::TextBlock => 'heroicon-o-document-text',
        };
    }

    /** Punya daftar opsi. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::MultipleChoice, self::Checkboxes, self::Dropdown], true);
    }

    /** Boleh opsi "Lainnya" (isian bebas). */
    public function allowsOther(): bool
    {
        return in_array($this, [self::MultipleChoice, self::Checkboxes], true);
    }

    /** Dinilai otomatis di mode kuis. */
    public function isAutoGradable(): bool
    {
        return in_array($this, [self::MultipleChoice, self::Checkboxes, self::Dropdown, self::ShortText], true);
    }

    /** Dinilai manual (esai/upload) di mode kuis. */
    public function isManuallyGraded(): bool
    {
        return in_array($this, [self::Paragraph, self::FileUpload], true);
    }

    public function acceptsAnswer(): bool
    {
        return $this !== self::TextBlock;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
