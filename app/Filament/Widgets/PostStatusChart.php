<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/** Konten per status (donat): Super Admin, Koordinator Medpub, Pembina. Lingkup = Post::visibleTo. */
class PostStatusChart extends ChartWidget
{
    protected static ?int $sort = 30;

    protected ?string $heading = 'Konten per status';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada konten';

    protected ?string $emptyStateDescription = 'Konten Medpub, press release, dan kajian publik akan tampil di sini.';

    private const COLORS = [
        'draft' => '#B6B6B7',
        'pending_coordinator' => '#FFDB1A',
        'pending_leader' => '#F59E0B',
        'approved' => '#000078',
        'published' => '#232361',
        'revision' => '#FB923C',
        'rejected' => '#DC2626',
    ];

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active
            && (PostPolicy::seesAllDrafts($user) || $user->checkPermissionTo('report.view'));
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        $counts = Post::query()->visibleTo($user)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return [];
        }

        $statuses = array_values(array_filter(PostStatus::cases(), fn (PostStatus $s) => ($counts[$s->value] ?? 0) > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn (PostStatus $s) => (int) $counts[$s->value], $statuses),
                'backgroundColor' => array_map(fn (PostStatus $s) => self::COLORS[$s->value], $statuses),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (PostStatus $s) => $s->label(), $statuses),
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
