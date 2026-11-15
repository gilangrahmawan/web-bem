<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Konten terbit per bulan, 12 bulan terakhir (batang). Data publik → aman untuk semua pembuat konten. */
class PostsPublishedChart extends ChartWidget
{
    protected static ?int $sort = 31;

    protected ?string $heading = 'Konten terbit per bulan';

    protected ?string $description = '12 bulan terakhir';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada konten terbit';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada konten yang diterbitkan.';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active
            && (PostStatusChart::canView() || PostPolicy::allowedTypes($user) !== [] || $user->checkPermissionTo('post.publish'));
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $first = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $dates = Post::query()->published()->where('published_at', '>=', $first)->pluck('published_at');

        if ($dates->isEmpty()) {
            return [];
        }

        $buckets = [];

        for ($i = 0; $i < 12; $i++) {
            $buckets[$first->addMonths($i)->format('Y-m')] = 0;
        }

        foreach ($dates as $date) {
            $key = CarbonImmutable::parse($date)->format('Y-m');

            if (isset($buckets[$key])) {
                $buckets[$key]++;
            }
        }

        return [
            'datasets' => [[
                'label' => 'Konten terbit',
                'data' => array_values($buckets),
                'backgroundColor' => BrandColors::NAVY,
                'borderRadius' => 4,
            ]],
            'labels' => array_map(fn (string $m) => CarbonImmutable::parse($m.'-01')->locale('id')->translatedFormat('M Y'), array_keys($buckets)),
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
