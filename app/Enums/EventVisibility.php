<?php

namespace App\Enums;

/** Diturunkan otomatis dari kategori agenda (lihat EventCategory::visibility()). */
enum EventVisibility: string
{
    case Public = 'public';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Publik',
            self::Internal => 'Internal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Internal => 'gray',
        };
    }
}
