<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use App\Services\Drive\DriveAccessResolver;
use Filament\Facades\Filament;

/**
 * Widget Drive yang mengikuti hak lihat: pengelola Drive & Pembina melihat semua; pengurus lain hanya
 * folder yang dibagikan kepadanya (widget disembunyikan bila tidak ada satu pun). Pengaju tidak pernah.
 */
trait ViewsDrive
{
    public static function canView(): bool
    {
        $user = self::driveUser();

        if (! $user) {
            return false;
        }

        $access = app(DriveAccessResolver::class);

        return $access->canUseDrive($user) && ($access->seesAll($user) || $access->visibleFolderIds($user) !== []);
    }

    protected static function driveUser(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** @return list<int>|null null = semua folder (termasuk root) */
    protected function visibleFolders(): ?array
    {
        return app(DriveAccessResolver::class)->visibleFolderIds(self::driveUser());
    }
}
