<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Jumlah pesan per hari (7 hari) di percakapan yang diikuti user — hanya hitungan, tanpa isi. */
class ChatActivityChart extends ChartWidget
{
    protected static ?int $sort = 71;

    protected ?string $heading = 'Aktivitas chat 7 hari';

    protected ?string $maxHeight = '240px';

    protected ?string $emptyStateHeading = 'Belum ada pesan';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada pesan di percakapan Anda dalam 7 hari terakhir.';

    public static function canView(): bool
    {
        return ChatStatsWidget::canView();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $start = CarbonImmutable::today()->subDays(6);

        $dates = Message::query()
            ->whereIn('conversation_id', fn ($q) => $q->select('conversation_id')->from('conversation_members')->where('user_id', $user->getKey()))
            ->where('created_at', '>=', $start)
            ->pluck('created_at');

        if ($dates->isEmpty()) {
            return [];
        }

        $buckets = [];

        for ($i = 0; $i < 7; $i++) {
            $buckets[$start->addDays($i)->format('Y-m-d')] = 0;
        }

        foreach ($dates as $date) {
            $key = CarbonImmutable::parse($date)->format('Y-m-d');

            if (isset($buckets[$key])) {
                $buckets[$key]++;
            }
        }

        return [
            'datasets' => [[
                'label' => 'Pesan',
                'data' => array_values($buckets),
                'borderColor' => BrandColors::NAVY,
                'backgroundColor' => 'rgba(35, 35, 97, 0.12)',
                'pointBackgroundColor' => BrandColors::YELLOW,
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $d) => CarbonImmutable::parse($d)->locale('id')->translatedFormat('D j/n'), array_keys($buckets)),
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
