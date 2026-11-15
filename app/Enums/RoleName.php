<?php

namespace App\Enums;

/**
 * Role bawaan (AGENTS.md BAGIAN D1). Slug disimpan di tabel `roles` Spatie.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Sekretaris = 'sekretaris';
    case Bendahara = 'bendahara';
    case KoordinatorKajian = 'koordinator_kajian';
    case AnggotaKajian = 'anggota_kajian';
    case KoordinatorMedpub = 'koordinator_medpub';
    case AnggotaMedpub = 'anggota_medpub';
    case KoordinatorHumas = 'koordinator_humas';
    case AnggotaHumas = 'anggota_humas';
    case Pembina = 'pembina';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin (Ketua & Wakil)',
            self::Sekretaris => 'Sekretaris',
            self::Bendahara => 'Bendahara',
            self::KoordinatorKajian => 'Koordinator Kajian',
            self::AnggotaKajian => 'Anggota Kajian',
            self::KoordinatorMedpub => 'Koordinator Medpub',
            self::AnggotaMedpub => 'Anggota Medpub',
            self::KoordinatorHumas => 'Koordinator Humas',
            self::AnggotaHumas => 'Anggota Humas',
            self::Pembina => 'Pembina',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Sekretaris, self::Bendahara => 'primary',
            self::KoordinatorKajian, self::KoordinatorMedpub, self::KoordinatorHumas => 'warning',
            self::Pembina => 'gray',
            default => 'info',
        };
    }

    /** Slug divisi default untuk role ini (null = tanpa divisi). */
    public function divisionSlug(): ?string
    {
        return match ($this) {
            self::SuperAdmin, self::Sekretaris, self::Bendahara => 'bph',
            self::KoordinatorKajian, self::AnggotaKajian => 'kajian',
            self::KoordinatorMedpub, self::AnggotaMedpub => 'medpub',
            self::KoordinatorHumas, self::AnggotaHumas => 'humas',
            self::Pembina => null,
        };
    }

    public static function labelFor(string $role): string
    {
        return self::tryFrom($role)?->label() ?? str($role)->headline()->toString();
    }

    public static function colorFor(string $role): string
    {
        return self::tryFrom($role)?->color() ?? 'gray';
    }
}
