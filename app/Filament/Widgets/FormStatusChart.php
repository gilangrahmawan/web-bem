<?php

namespace App\Filament\Widgets;

use App\Enums\FormStatus;
use App\Filament\Widgets\Concerns\ViewsForms;
use Filament\Widgets\ChartWidget;

/** Form per status (donat). Hanya form yang terlihat oleh user. */
class FormStatusChart extends ChartWidget
{
    use ViewsForms;

    protected static ?int $sort = 61;

    protected ?string $heading = 'Form per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada form';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah form pertama dibuat.';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = $this->formsQuery()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return [];
        }

        $statuses = array_values(array_filter(FormStatus::cases(), fn (FormStatus $s) => ($counts[$s->value] ?? 0) > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn (FormStatus $s) => (int) $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (FormStatus $s) => $s->chartColor(), $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (FormStatus $s) => $s->label(), $statuses),
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
