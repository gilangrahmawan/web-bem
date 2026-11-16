<?php

namespace App\Enums;

/** Penerima izin folder Drive (folder_permissions.grantee_type). */
enum DriveGranteeType: string
{
    case Role = 'role';
    case Division = 'division';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Role => 'Role',
            self::Division => 'Divisi',
            self::User => 'Pengurus',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
