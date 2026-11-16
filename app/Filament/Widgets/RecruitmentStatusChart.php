<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicantStatus;
use App\Filament\Widgets\Concerns\ViewsRecruitment;
use Filament\Widgets\ChartWidget;

/** Pendaftar oprec per status (donat). */
class RecruitmentStatusChart extends ChartWidget
{
    use ViewsRecruitment;

    protected static ?int $sort = 51;

    protected ?string $heading = 'Pendaftar oprec per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada pendaftar';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada pendaftar pada gelombang ini.';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = $this->applicantsQuery()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return [];
        }

        $statuses = array_values(array_filter(ApplicantStatus::cases(), fn (ApplicantStatus $s) => ($counts[$s->value] ?? 0) > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn (ApplicantStatus $s) => (int) $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (ApplicantStatus $s) => $s->chartColor(), $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (ApplicantStatus $s) => $s->label(), $statuses),
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
