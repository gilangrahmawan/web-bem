<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\User;
use App\Services\KasService;
use Filament\Facades\Filament;

/**
 * Widget kas keseluruhan: hanya untuk pemegang kas.view_all (bendahara, super_admin, pembina — read-only).
 * Koordinator/anggota tidak melihat data kas orang lain; mereka hanya mendapat widget Kas Saya.
 */
trait ViewsAllKas
{
    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && $user->can('kas.view_all');
    }

    protected function kas(): KasService
    {
        return app(KasService::class);
    }
}
