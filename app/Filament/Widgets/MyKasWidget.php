<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\KasService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Kas Saya: progres minggu yang sudah dibayar, sisa tunggakan, dan iuran yang belum lunas.
 * Selalu hanya data milik user yang login (tidak ada parameter user dari browser).
 * Tampil untuk semua pengurus wajib kas (Pembina tidak wajib kas).
 */
class MyKasWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.my-kas';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && app(KasService::class)->isPayer($user);
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $kas = app(KasService::class);
        $summary = $kas->summaryFor($user);

        return [
            'problem' => $kas->setupProblem(),
            'summary' => $summary,
            'amount' => $kas->weeklyAmount(),
            'firstWeek' => $summary?->firstWeek ? $kas->week($summary->firstWeek) : null,
            'dues' => $kas->outstandingDuesFor($user),
        ];
    }
}
