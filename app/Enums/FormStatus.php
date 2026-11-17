<?php

namespace App\Enums;

/** Status form: draft → published → closed. */
enum FormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
            self::Closed => 'Ditutup',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Closed => 'danger',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Draft => '#B6B6B7',
            self::Published => '#232361',
            self::Closed => '#DC2626',
        };
    }
}
