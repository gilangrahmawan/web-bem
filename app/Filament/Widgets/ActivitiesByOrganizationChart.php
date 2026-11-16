<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsAllActivities;
use App\Models\StudentActivity;
use Filament\Widgets\ChartWidget;

/** Jumlah kegiatan per organisasi (10 teratas, batang horizontal) — BEM. Draft tidak dihitung. */
class ActivitiesByOrganizationChart extends ChartWidget
{
    use ViewsAllActivities;

    protected static ?int $sort = 42;

    protected ?string $heading = 'Kegiatan per organisasi';

    protected ?string $maxHeight = '280px';

    protected ?string $emptyStateHeading = 'Belum ada kegiatan diajukan';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada proposal yang diajukan.';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = StudentActivity::query()
            ->where('status', '!=', 'draft')
            ->join('organizations', 'organizations.id', '=', 'student_activities.organization_id')
            ->selectRaw('organizations.name as org, COUNT(*) as total')
            ->groupBy('organizations.name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'org');

        if ($rows->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Kegiatan',
                'data' => $rows->values()->map(fn ($v) => (int) $v)->all(),
                'backgroundColor' => BrandColors::NAVY,
                'borderRadius' => 4,
            ]],
            'labels' => $rows->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
