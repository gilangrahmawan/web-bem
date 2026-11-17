<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsForms;
use App\Models\FormResponse;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Jawaban form masuk per hari, 30 hari terakhir (garis). Hanya form yang terlihat oleh user. */
class FormResponsesDailyChart extends ChartWidget
{
    use ViewsForms;

    protected static ?int $sort = 60;

    protected ?string $heading = 'Jawaban form per hari';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada jawaban';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada jawaban form dalam 30 hari terakhir.';

    public function getDescription(): ?string
    {
        return '30 hari terakhir';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = CarbonImmutable::now()->subDays(29)->startOfDay();
        $dates = FormResponse::query()
            ->whereIn('form_id', $this->formsQuery()->select('id'))
            ->where('submitted_at', '>=', $start)
            ->pluck('submitted_at');

        if ($dates->isEmpty()) {
            return [];
        }

        $buckets = [];

        for ($day = $start; $day->lte(now()); $day = $day->addDay()) {
            $buckets[$day->format('Y-m-d')] = 0;
        }

        foreach ($dates as $date) {
            $key = CarbonImmutable::parse($date)->format('Y-m-d');

            if (isset($buckets[$key])) {
                $buckets[$key]++;
            }
        }

        return [
            'datasets' => [[
                'label' => 'Jawaban',
                'data' => array_values($buckets),
                'borderColor' => BrandColors::NAVY,
                'backgroundColor' => 'rgba(35, 35, 97, 0.12)',
                'pointBackgroundColor' => BrandColors::YELLOW,
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $d) => CarbonImmutable::parse($d)->locale('id')->translatedFormat('j M'), array_keys($buckets)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
