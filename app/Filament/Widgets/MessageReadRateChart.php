<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Models\Announcement;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Str;

/**
 * Tingkat baca 8 pengumuman/surat terbaru (%).
 * Pembuat (Sekretaris/Humas) hanya melihat kirimannya sendiri; super_admin & Pembina melihat semua.
 */
class MessageReadRateChart extends ChartWidget
{
    protected static ?int $sort = 22;

    protected ?string $heading = 'Tingkat baca pengumuman & surat';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada pengumuman atau surat';

    protected ?string $emptyStateDescription = 'Persentase pembaca muncul setelah ada pesan yang dikirim.';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && ($user->can('announcement.create') || $user->can('letter.create') || $user->can('report.view'));
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $seesAll = $user->isSuperAdmin() || $user->checkPermissionTo('report.view');

        $messages = Announcement::query()
            ->when(! $seesAll, fn ($q) => $q->where('created_by', $user->getKey()))
            ->withCount(['recipients', 'recipients as read_count' => fn ($q) => $q->whereNotNull('read_at')])
            ->latest()
            ->limit(8)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sudah dibaca (%)',
                    'data' => $messages->map(fn (Announcement $m) => $m->recipients_count > 0 ? (int) round($m->read_count / $m->recipients_count * 100) : 0)->all(),
                    'backgroundColor' => $messages->map(fn (Announcement $m) => $m->type->value === 'letter' ? BrandColors::YELLOW : BrandColors::NAVY)->all(),
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $messages->map(fn (Announcement $m) => Str::limit($m->title, 24))->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.parsed.y + '% penerima sudah membaca' } } },
                scales: { y: { min: 0, max: 100, ticks: { callback: (v) => v + '%' } } },
            }
        JS);
    }
}
