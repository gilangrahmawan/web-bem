<?php

namespace App\Enums;

/** Jenis percakapan chat (AGENTS.md C6). */
enum ConversationType: string
{
    case Personal = 'personal';
    case Division = 'division';       // otomatis: Kajian, Medpub, Humas, BPH, Semua Pengurus
    case Organization = 'organization'; // otomatis: akun pengaju per organisasi (bukan tipe BEM)
    case Custom = 'custom';           // dibuat pengguna; pembuat = admin grup

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Division => 'Grup divisi',
            self::Organization => 'Grup organisasi',
            self::Custom => 'Grup',
        };
    }

    public function isAutomatic(): bool
    {
        return in_array($this, [self::Division, self::Organization], true);
    }
}
