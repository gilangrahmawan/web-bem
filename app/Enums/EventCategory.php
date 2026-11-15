<?php

namespace App\Enums;

/**
 * Kategori agenda kalender (AGENTS.md C4). Satu tabel `events`, dua tampilan:
 * kategori publik tampil di /kalender, sisanya hanya di dashboard.
 */
enum EventCategory: string
{
    case Holiday = 'holiday';
    case Campus = 'campus';
    case Bem = 'bem';
    case Ukm = 'ukm';
    case Meeting = 'meeting';
    case Content = 'content';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Hari Besar',
            self::Campus => 'Akademik Kampus',
            self::Bem => 'Kegiatan BEM',
            self::Ukm => 'Kegiatan UKM',
            self::Meeting => 'Rapat',
            self::Content => 'Tugas Konten Medpub',
            self::Internal => 'Internal Lainnya',
        };
    }

    /** Warna badge Filament. */
    public function color(): string
    {
        return match ($this) {
            self::Holiday => 'danger',
            self::Campus => 'info',
            self::Bem => 'primary',
            self::Ukm => 'success',
            self::Meeting => 'warning',
            self::Content => 'info',
            self::Internal => 'gray',
        };
    }

    /** Warna agenda di FullCalendar (publik & dashboard). */
    public function hex(): string
    {
        return match ($this) {
            self::Holiday => '#dc2626',
            self::Campus => '#0e7490',
            self::Bem => '#232361',
            self::Ukm => '#15803d',
            self::Meeting => '#b45309',
            self::Content => '#7c3aed',
            self::Internal => '#64748b',
        };
    }

    public function isPublic(): bool
    {
        return in_array($this, self::publicCases(), true);
    }

    public function visibility(): EventVisibility
    {
        return $this->isPublic() ? EventVisibility::Public : EventVisibility::Internal;
    }

    /** Hanya agenda UKM & BEM yang diperiksa bentroknya (AGENTS.md C4). */
    public function checksConflict(): bool
    {
        return in_array($this, self::conflictCases(), true);
    }

    /**
     * Agenda milik satu divisi: koordinator hanya boleh mengelola agenda divisinya (AGENTS.md D4).
     */
    public function isDivisionScoped(): bool
    {
        return in_array($this, [self::Meeting, self::Content, self::Internal], true);
    }

    /** Permission kustom untuk menambah/mengubah/menghapus agenda kategori ini. */
    public function requiredPermission(): string
    {
        return match ($this) {
            self::Holiday, self::Campus, self::Bem => 'event.create_public',
            self::Ukm => 'event.create_ukm',
            // Keputusan pemilik: kategori `internal` memakai izin yang sama dengan rapat.
            self::Meeting, self::Internal => 'event.create_meeting',
            self::Content => 'event.assign_task',
        };
    }

    /** @return list<self> */
    public static function publicCases(): array
    {
        return [self::Holiday, self::Campus, self::Bem, self::Ukm];
    }

    /** @return list<self> */
    public static function conflictCases(): array
    {
        return [self::Ukm, self::Bem];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
