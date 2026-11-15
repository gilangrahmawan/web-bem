<?php

namespace App\Enums;

/** Tipe iuran tambahan. */
enum DueType: string
{
    case Once = 'once';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Sekali bayar',
            self::Monthly => 'Bulanan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Once => 'gray',
            self::Monthly => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
