<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsAllKas;
use Filament\Widgets\ChartWidget;

/** Pengurus lunas vs menunggak (donat). */
class KasStatusChart extends ChartWidget
{
    use ViewsAllKas;

    protected static ?int $sort = 12;

    protected ?string $heading = 'Lunas vs menunggak';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada data kas';

    public function getEmptyStateDescription(): ?string
    {
        return $this->kas()->setupProblem() ?? 'Belum ada pengurus wajib kas.';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        if ($this->kas()->setupProblem() !== null) {
            return [];
        }

        $totals = $this->kas()->totals();

        if ($totals['payers'] === 0) {
            return [];
        }

        return [
            'datasets' => [[
                'data' => [$totals['clear'], $totals['arrears_people']],
                'backgroundColor' => [BrandColors::NAVY, BrandColors::YELLOW],
                'borderWidth' => 0,
            ]],
            'labels' => ['Lunas', 'Menunggak'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
            'cutout' => '62%',
        ];
    }
}
