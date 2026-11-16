<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\BrandColors;
use App\Models\Division;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/** Super Admin: pengurus aktif dengan push notification aktif vs belum, per divisi. */
class PushAdoptionChart extends ChartWidget
{
    protected static ?int $sort = 72;

    protected ?string $heading = 'Pengurus dengan push notification aktif';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada pengurus aktif';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && $user->isSuperAdmin();
    }

    public function getDescription(): ?string
    {
        $staff = $this->staff();
        $on = $staff->filter(fn (User $u) => $u->push_subscriptions_count > 0)->count();

        return $staff->isEmpty() ? null : "{$on} dari {$staff->count()} pengurus (".round($on / $staff->count() * 100).'%)';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $staff = $this->staff();

        if ($staff->isEmpty()) {
            return [];
        }

        $divisions = Division::query()->orderBy('id')->pluck('name', 'id');
        $labels = [...$divisions->values()->all(), 'Tanpa divisi'];
        $on = [];
        $off = [];

        foreach ([...$divisions->keys()->all(), null] as $id) {
            $group = $staff->where('division_id', $id);
            $on[] = $group->filter(fn (User $u) => $u->push_subscriptions_count > 0)->count();
            $off[] = $group->filter(fn (User $u) => $u->push_subscriptions_count === 0)->count();
        }

        return [
            'datasets' => [
                ['label' => 'Aktif', 'data' => $on, 'backgroundColor' => BrandColors::NAVY, 'borderRadius' => 4],
                ['label' => 'Belum', 'data' => $off, 'backgroundColor' => BrandColors::MUTED, 'borderRadius' => 4],
            ],
            'labels' => $labels,
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

    /** @return Collection<int, User> */
    private function staff(): Collection
    {
        return User::query()->where('is_active', true)->with('roles')->withCount('pushSubscriptions')->get()
            ->filter(fn (User $u) => ! $u->isApplicant() && $u->roles->isNotEmpty())
            ->values();
    }
}
