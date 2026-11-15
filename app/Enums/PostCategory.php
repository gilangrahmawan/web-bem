<?php

namespace App\Enums;

enum PostCategory: string
{
    case Activity = 'activity';
    case Announcement = 'announcement';
    case Achievement = 'achievement';
    case Opinion = 'opinion';

    public function label(): string
    {
        return match ($this) {
            self::Activity => 'Kegiatan',
            self::Announcement => 'Pengumuman',
            self::Achievement => 'Prestasi',
            self::Opinion => 'Opini',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activity => 'primary',
            self::Announcement => 'warning',
            self::Achievement => 'success',
            self::Opinion => 'info',
        };
    }

    /** Slug Indonesia untuk filter di URL publik (?kategori=kegiatan). */
    public function slug(): string
    {
        return str($this->label())->slug()->toString();
    }

    public static function fromSlug(?string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }
}
