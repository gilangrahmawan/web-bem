<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityStatus;
use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Models\StudentActivity;
use App\Models\User;
use App\Support\Format;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tenggat LPJ terdekat untuk kegiatan organisasi saya — pengaju kegiatan. */
class LpjDeadlineWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Tenggat LPJ';

    public static function canView(): bool
    {
        return MyActivitiesChart::canView();
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        $waiting = StudentActivity::query()->visibleTo($user)
            ->whereIn('status', [ActivityStatus::Approved->value, ActivityStatus::LpjRevision->value])
            ->get()
            ->sortBy(fn (StudentActivity $a) => $a->lpjDueOn()->timestamp)
            ->values();

        if ($waiting->isEmpty()) {
            return [
                Stat::make('Tenggat LPJ terdekat', 'Tidak ada')
                    ->description('Belum ada kegiatan yang menunggu LPJ.')
                    ->icon(Heroicon::OutlinedCheckCircle)->color('success'),
            ];
        }

        $next = $waiting->first();
        $overdue = $waiting->filter(fn (StudentActivity $a) => $a->isLpjOverdue())->count();
        $days = (int) now()->startOfDay()->diffInDays($next->lpjDueOn(), false);

        return [
            Stat::make('Tenggat LPJ terdekat', Format::date($next->lpjDueOn()))
                ->description($next->name.' · '.($days < 0 ? 'terlambat '.abs($days).' hari' : ($days === 0 ? 'hari ini' : "{$days} hari lagi")))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color($days < 0 ? 'danger' : ($days <= 3 ? 'warning' : 'primary'))
                ->url(StudentActivityResource::getUrl('view', ['record' => $next])),
            Stat::make('Menunggu LPJ', $waiting->count())->icon(Heroicon::OutlinedDocumentArrowUp),
            Stat::make('LPJ terlambat', $overdue)->icon(Heroicon::OutlinedExclamationTriangle)->color($overdue ? 'danger' : 'success'),
        ];
    }
}
