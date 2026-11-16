<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityStatus;
use App\Models\StudentActivity;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Kegiatan saya (organisasi saya) per status — pengaju kegiatan. */
class MyActivitiesChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Kegiatan organisasi saya per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada kegiatan';

    protected ?string $emptyStateDescription = 'Buat kegiatan di menu Kegiatan Saya lalu ajukan proposalnya.';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && $user->isApplicant();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        $counts = StudentActivity::query()->visibleTo($user)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return [];
        }

        $statuses = array_values(array_filter(ActivityStatus::cases(), fn (ActivityStatus $s) => ($counts[$s->value] ?? 0) > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn (ActivityStatus $s) => (int) $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (ActivityStatus $s) => $s->chartColor(), $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (ActivityStatus $s) => $s->label(), $statuses),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
            'cutout' => '60%',
        ];
    }
}
