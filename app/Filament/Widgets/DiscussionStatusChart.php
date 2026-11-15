<?php

namespace App\Filament\Widgets;

use App\Enums\DiscussionStatus;
use App\Models\Discussion;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Pembahasan Kajian per status (donat). Untuk Divisi Kajian, super_admin, dan Pembina. */
class DiscussionStatusChart extends ChartWidget
{
    protected static ?int $sort = 23;

    protected ?string $heading = 'Pembahasan Kajian per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada pembahasan';

    protected ?string $emptyStateDescription = 'Entri pembahasan Divisi Kajian akan tampil di sini.';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active
            && ($user->isSuperAdmin() || $user->checkPermissionTo('report.view') || $user->checkPermissionTo('discussion.create'));
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Discussion::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return [];
        }

        $statuses = DiscussionStatus::cases();

        return [
            'datasets' => [[
                'data' => array_map(fn (DiscussionStatus $s) => (int) ($counts[$s->value] ?? 0), $statuses),
                'backgroundColor' => array_map(fn (DiscussionStatus $s) => $s->chartColor(), $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (DiscussionStatus $s) => $s->label(), $statuses),
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
