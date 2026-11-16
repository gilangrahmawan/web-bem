<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityStatus;
use App\Filament\Widgets\Concerns\ViewsAllActivities;
use App\Models\StudentActivity;
use Filament\Widgets\ChartWidget;

/** Kegiatan per status (donat) — BEM. */
class ActivityStatusChart extends ChartWidget
{
    use ViewsAllActivities;

    protected static ?int $sort = 40;

    protected ?string $heading = 'Kegiatan per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada kegiatan';

    protected ?string $emptyStateDescription = 'Kegiatan yang diajukan organisasi akan tampil di sini.';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = StudentActivity::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

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
