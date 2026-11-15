<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsAllKas;
use App\Models\Due;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Progres tiap iuran tambahan (% terkumpul dari target semua peserta), batang horizontal. */
class DuesProgressChart extends ChartWidget
{
    use ViewsAllKas;

    protected static ?int $sort = 14;

    protected ?string $heading = 'Progres iuran tambahan';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada iuran tambahan';

    protected ?string $emptyStateDescription = 'Iuran seperti PDH atau tabungan jalan-jalan akan tampil di sini.';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $dues = Due::query()->orderBy('due_date')->limit(12)->get();

        if ($dues->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Terkumpul (%)',
                'data' => $dues->map(fn (Due $due): int => $this->kas()->dueProgress($due)['percent'])->all(),
                'backgroundColor' => BrandColors::NAVY,
                'borderRadius' => 4,
            ]],
            'labels' => $dues->pluck('name')->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => c.parsed.x + '% terkumpul' } },
                },
                scales: { x: { min: 0, max: 100, ticks: { callback: (v) => v + '%' } } },
            }
        JS);
    }
}
