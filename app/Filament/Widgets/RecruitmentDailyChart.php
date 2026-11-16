<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Filament\Widgets\Concerns\ViewsRecruitment;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Pendaftar oprec per hari selama gelombang terbaru dibuka (garis). */
class RecruitmentDailyChart extends ChartWidget
{
    use ViewsRecruitment;

    protected static ?int $sort = 52;

    protected ?string $heading = 'Pendaftar oprec per hari';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada pendaftar';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada pendaftar pada gelombang ini.';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $recruitment = self::latestRecruitment();
        $dates = $this->applicantsQuery()->pluck('created_at');

        if ($recruitment === null || $dates->isEmpty()) {
            return [];
        }

        $start = CarbonImmutable::parse($recruitment->opens_at)->startOfDay();
        $end = CarbonImmutable::parse(min($recruitment->closes_at, now()))->startOfDay();
        $buckets = [];

        for ($day = $start; $day->lte($end) && count($buckets) < 92; $day = $day->addDay()) {
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
                'label' => 'Pendaftar',
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
