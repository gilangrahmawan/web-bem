<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsAllKas;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Tunggakan kas per divisi (batang). */
class KasArrearsByDivisionChart extends ChartWidget
{
    use ViewsAllKas;

    protected static ?int $sort = 13;

    protected ?string $heading = 'Tunggakan per divisi';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Tidak ada tunggakan';

    public function getEmptyStateDescription(): ?string
    {
        return $this->kas()->setupProblem() ?? 'Semua pengurus sudah lunas.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        if ($this->kas()->setupProblem() !== null) {
            return [];
        }

        $byDivision = $this->kas()->arrearsByDivision();

        if (array_sum($byDivision) === 0) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Tunggakan (Rp)',
                'data' => array_values($byDivision),
                'backgroundColor' => BrandColors::YELLOW,
                'borderColor' => BrandColors::NAVY,
                'borderWidth' => 1,
                'borderRadius' => 4,
            ]],
            'labels' => array_keys($byDivision),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => 'Rp' + c.parsed.y.toLocaleString('id-ID') } },
                },
                scales: { y: { beginAtZero: true, ticks: { callback: (v) => 'Rp' + v.toLocaleString('id-ID') } } },
            }
        JS);
    }
}
