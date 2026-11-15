<?php

namespace App\Enums;

/**
 * Kategori agenda kalender (AGENTS.md C4). Satu tabel `events`, dua tampilan:
 * kategori publik tampil di /kalender, sisanya hanya di dashboard.
 *
 * Urutan case = urutan legenda & filter. Warna mengikuti legenda kalender BEM;
 * warna teks Libur Nasional, Cuti Bersama, dan Hari Jadi Daerah digelapkan agar kontras ≥ 4,5:1
 * (keputusan pemilik). Kontras semua pasangan diuji di tests/Feature/Calendar/CategoryColorTest.php.
 */
enum EventCategory: string
{
    case Holiday = 'holiday';
    case CollectiveLeave = 'collective_leave';
    case Commemoration = 'commemoration';
    case RegionalAnniversary = 'regional_anniversary';
    case Campus = 'campus';
    case UniversityAnniversary = 'university_anniversary';
    case Bem = 'bem';
    case Ukm = 'ukm';
    case Meeting = 'meeting';
    case Content = 'content';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Libur Nasional',
            self::CollectiveLeave => 'Cuti Bersama',
            self::Commemoration => 'Hari Peringatan Nasional',
            self::RegionalAnniversary => 'Hari Jadi Daerah',
            self::Campus => 'Kalender Akademik UBHINUS',
            self::UniversityAnniversary => 'HUT UBHINUS',
            self::Bem => 'Kegiatan BEM',
            self::Ukm => 'Kegiatan UKM',
            self::Meeting => 'Rapat',
            self::Content => 'Tugas Konten Medpub',
            self::Internal => 'Internal Lainnya',
        };
    }

    /** Keterangan singkat untuk legenda (null = cukup label). */
    public function legendHint(): ?string
    {
        return match ($this) {
            self::RegionalAnniversary => 'Jatim, Kota Malang, Kab. Malang, Kota Batu',
            default => null,
        };
    }

    /** Warna badge Filament (modal detail / tabel). */
    public function color(): string
    {
        return match ($this) {
            self::Holiday => 'danger',
            self::CollectiveLeave, self::Commemoration => 'warning',
            self::RegionalAnniversary, self::Campus => 'info',
            self::UniversityAnniversary, self::Bem => 'primary',
            self::Ukm => 'success',
            self::Meeting, self::Content, self::Internal => 'gray',
        };
    }

    /** Warna latar agenda di FullCalendar (publik & dashboard). */
    public function background(): string
    {
        return match ($this) {
            self::Holiday => '#FFABAB',
            self::CollectiveLeave => '#F8CBAD',
            self::Commemoration => '#FFE699',
            self::RegionalAnniversary => '#A9E4EF',
            self::Campus => '#9BC2E6',
            self::UniversityAnniversary => '#1F3864',
            self::Bem => '#B48FE0',
            self::Ukm => '#15803D',
            self::Meeting => '#0F766E',
            self::Content => '#DB2777',
            self::Internal => '#64748B',
        };
    }

    /** Warna teks agenda. */
    public function text(): string
    {
        return match ($this) {
            self::Holiday => '#9C0006',
            self::CollectiveLeave => '#843C0C',
            self::Commemoration => '#7F6000',
            self::RegionalAnniversary => '#155A63',
            self::Campus => '#1F3864',
            self::Bem => '#3B1F5C',
            self::UniversityAnniversary, self::Ukm, self::Meeting, self::Content, self::Internal => '#FFFFFF',
        };
    }

    /** Garis kiri tebal (null = tanpa garis). */
    public function accent(): ?string
    {
        return match ($this) {
            self::Campus => '#2F5597',
            self::Bem => '#7030A0',
            default => null,
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
     * Hanya dikelola HolidaySeeder: tidak bisa dibuat/diubah/dihapus dari dashboard oleh siapa pun,
     * termasuk super_admin (keputusan pemilik). Koreksi dilakukan di daftar HolidaySeeder.
     */
    public function isSeederOnly(): bool
    {
        return $this === self::CollectiveLeave;
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
            self::Holiday, self::CollectiveLeave, self::Commemoration, self::RegionalAnniversary,
            self::Campus, self::UniversityAnniversary, self::Bem => 'event.create_public',
            self::Ukm => 'event.create_ukm',
            // Keputusan pemilik: kategori `internal` memakai izin yang sama dengan rapat.
            self::Meeting, self::Internal => 'event.create_meeting',
            self::Content => 'event.assign_task',
        };
    }

    /** @return list<self> */
    public static function publicCases(): array
    {
        return [
            self::Holiday, self::CollectiveLeave, self::Commemoration, self::RegionalAnniversary,
            self::Campus, self::UniversityAnniversary, self::Bem, self::Ukm,
        ];
    }

    /**
     * Kategori yang boleh masuk lewat impor CSV (keputusan pemilik).
     * `holiday` & `collective_leave` sengaja tidak termasuk: sumbernya hanya HolidaySeeder.
     *
     * @return list<self>
     */
    public static function importableCases(): array
    {
        return [
            self::Campus, self::Commemoration, self::RegionalAnniversary,
            self::UniversityAnniversary, self::Bem, self::Ukm,
        ];
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
