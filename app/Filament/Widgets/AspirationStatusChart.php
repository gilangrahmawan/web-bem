<?php

namespace App\Filament\Widgets;

use App\Enums\AspirationStatus;
use App\Models\Aspiration;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Aspirasi per status (donat). Lingkup data = Aspiration::visibleTo (Kajian hanya yang diteruskan). */
class AspirationStatusChart extends ChartWidget
{
    protected static ?int $sort = 20;

    protected ?string $heading = 'Aspirasi per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada aspirasi';

    protected ?string $emptyStateDescription = 'Aspirasi dari form publik akan tampil di sini.';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('viewAny', Aspiration::class);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        $counts = Aspiration::query()->visibleTo($user)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')
            ->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return [];
        }

        $statuses = array_values(array_filter(AspirationStatus::cases(), fn (AspirationStatus $s) => ($counts[$s->value] ?? 0) > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn (AspirationStatus $s) => (int) $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (AspirationStatus $s) => $s->chartColor(), $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (AspirationStatus $s) => $s->label(), $statuses),
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
