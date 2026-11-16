<?php

namespace App\Enums;

/** Jenis organisasi pengaju kegiatan (keputusan Fase 7). */
enum OrganizationType: string
{
    case Ukm = 'ukm';
    case Hmj = 'hmj';
    case Bem = 'bem';
    case Committee = 'panitia';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ukm => 'UKM',
            self::Hmj => 'HMJ',
            self::Bem => 'BEM',
            self::Committee => 'Panitia',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ukm => 'success',
            self::Hmj => 'info',
            self::Bem => 'primary',
            self::Committee => 'warning',
            self::Other => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
