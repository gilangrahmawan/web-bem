<?php

namespace App\Enums;

/** Kategori aspirasi (keputusan pemilik, Fase 5). */
enum AspirationCategory: string
{
    case Academic = 'academic';
    case Facility = 'facility';
    case StudentAffairs = 'student_affairs';
    case Finance = 'finance';
    case CampusService = 'campus_service';
    case BemFeedback = 'bem_feedback';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Akademik',
            self::Facility => 'Fasilitas & Sarana',
            self::StudentAffairs => 'Kemahasiswaan & Organisasi',
            self::Finance => 'Keuangan/UKT',
            self::CampusService => 'Layanan Kampus',
            self::BemFeedback => 'Masukan untuk BEM',
            self::Other => 'Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
