<?php

namespace App\Enums;

/** Pengumuman (Sekretaris) atau Surat & masukan (Humas). Satu tabel `announcements`. */
enum AnnouncementType: string
{
    case Announcement = 'announcement';
    case Letter = 'letter';

    public function label(): string
    {
        return match ($this) {
            self::Announcement => 'Pengumuman',
            self::Letter => 'Surat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Announcement => 'info',
            self::Letter => 'warning',
        };
    }

    /** Permission untuk membuat/mengelola tipe ini. */
    public function permission(): string
    {
        return match ($this) {
            self::Announcement => 'announcement.create',
            self::Letter => 'letter.create',
        };
    }
}
