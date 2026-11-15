<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsAllKas;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Kas terkumpul per minggu kas (batang). */
class KasWeeklyChart extends ChartWidget
{
    use ViewsAllKas;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Kas masuk per minggu';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada data kas';

    public function getEmptyStateDescription(): ?string
    {
        return $this->kas()->setupProblem() ?? 'Pembayaran kas yang dicatat Bendahara akan tampil di sini.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $perWeek = $this->kas()->collectedPerWeek();

        if ($perWeek === [] || array_sum($perWeek) === 0) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Kas masuk (Rp)',
                'data' => array_values($perWeek),
                'backgroundColor' => BrandColors::NAVY,
                'borderRadius' => 4,
            ]],
            'labels' => array_map(fn (int $n): string => "M{$n}", array_keys($perWeek)),
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
