<?php

namespace App\Enums;

/**
 * Tingkat akses folder Drive (diwariskan ke subfolder). view < upload < manage.
 * - view: buka, unduh, preview.
 * - upload: + buat folder/berkas/dokumen, ubah & hapus item MILIKNYA sendiri.
 * - manage: semua aksi, termasuk bagikan, pindah, dan hapus item orang lain.
 */
enum DriveAccess: string
{
    case View = 'view';
    case Upload = 'upload';
    case Manage = 'manage';

    public function rank(): int
    {
        return match ($this) {
            self::View => 1,
            self::Upload => 2,
            self::Manage => 3,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public static function max(?self $a, ?self $b): ?self
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a->rank() >= $b->rank() ? $a : $b;
    }

    public function label(): string
    {
        return match ($this) {
            self::View => 'Lihat',
            self::Upload => 'Unggah',
            self::Manage => 'Kelola',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::View => 'Buka, unduh, dan pratinjau',
            self::Upload => 'Tambah folder/berkas/dokumen, ubah & hapus miliknya sendiri',
            self::Manage => 'Semua aksi termasuk bagikan, pindah, dan hapus milik orang lain',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::View => 'gray',
            self::Upload => 'info',
            self::Manage => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label().' — '.$c->description()])->all();
    }
}
