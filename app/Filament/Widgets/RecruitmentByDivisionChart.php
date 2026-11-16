<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Recruitments\RecruitmentResource;
use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsRecruitment;
use App\Models\Division;
use Filament\Widgets\ChartWidget;

/** Pendaftar oprec per divisi: pilihan 1 & pilihan 2 (batang bertumpuk). */
class RecruitmentByDivisionChart extends ChartWidget
{
    use ViewsRecruitment;

    protected static ?int $sort = 50;

    protected ?string $heading = 'Pendaftar oprec per divisi';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada pendaftar';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada pendaftar pada gelombang ini.';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $first = $this->applicantsQuery()->selectRaw('first_choice_division_id as d, COUNT(*) as total')->groupBy('first_choice_division_id')->pluck('total', 'd');
        $second = $this->applicantsQuery()->whereNotNull('second_choice_division_id')
            ->selectRaw('second_choice_division_id as d, COUNT(*) as total')->groupBy('second_choice_division_id')->pluck('total', 'd');

        if ($first->sum() === 0) {
            return [];
        }

        $divisions = Division::query()->whereIn('slug', RecruitmentResource::OPENABLE_DIVISIONS)
            ->orWhereIn('id', $first->keys()->merge($second->keys())->all())
            ->orderBy('name')->get(['id', 'name']);

        return [
            'datasets' => [
                [
                    'label' => 'Pilihan 1',
                    'data' => $divisions->map(fn (Division $d) => (int) ($first[$d->id] ?? 0))->all(),
                    'backgroundColor' => BrandColors::NAVY,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Pilihan 2',
                    'data' => $divisions->map(fn (Division $d) => (int) ($second[$d->id] ?? 0))->all(),
                    'backgroundColor' => BrandColors::YELLOW,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $divisions->pluck('name')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
