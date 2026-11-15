<?php

namespace App\Enums;

enum PostType: string
{
    case Article = 'article';
    case Social = 'social';
    case PressRelease = 'press_release';
    case Study = 'study';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Artikel',
            self::Social => 'Postingan',
            self::PressRelease => 'Press Release',
            self::Study => 'Kajian Publik',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Article => 'primary',
            self::Social => 'info',
            self::PressRelease => 'warning',
            self::Study => 'success',
        };
    }

    /** Tipe yang tampil di halaman /berita. */
    public static function newsTypes(): array
    {
        return [self::Article->value, self::Social->value];
    }

    /** Tipe yang tampil di halaman /kajian. */
    public static function studyTypes(): array
    {
        return [self::PressRelease->value, self::Study->value];
    }
}
