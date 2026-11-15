<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Models\Aspiration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Aspirasi masuk per minggu, 12 minggu terakhir (batang). Lingkup = Aspiration::visibleTo. */
class AspirationWeeklyChart extends ChartWidget
{
    protected static ?int $sort = 21;

    protected ?string $heading = 'Aspirasi masuk per minggu';

    protected ?string $description = '12 minggu terakhir';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada aspirasi masuk';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada aspirasi dalam 12 minggu terakhir.';

    public static function canView(): bool
    {
        return AspirationStatusChart::canView();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $firstMonday = CarbonImmutable::now()->startOfWeek()->subWeeks(11);

        $dates = Aspiration::query()->visibleTo($user)
            ->where('created_at', '>=', $firstMonday)
            ->pluck('created_at');

        if ($dates->isEmpty()) {
            return [];
        }

        $buckets = [];

        for ($i = 0; $i < 12; $i++) {
            $buckets[$firstMonday->addWeeks($i)->format('Y-m-d')] = 0;
        }

        foreach ($dates as $date) {
            $key = CarbonImmutable::parse($date)->startOfWeek()->format('Y-m-d');

            if (isset($buckets[$key])) {
                $buckets[$key]++;
            }
        }

        return [
            'datasets' => [[
                'label' => 'Aspirasi',
                'data' => array_values($buckets),
                'backgroundColor' => BrandColors::NAVY,
                'borderRadius' => 4,
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
