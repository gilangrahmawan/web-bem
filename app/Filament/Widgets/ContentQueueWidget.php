<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Antrean persetujuan konten.
 * - Koordinator Medpub: menunggu persetujuannya, siap terbit, terbit tapi belum diposting ke medsos.
 * - Ketua/Wakil: menunggu persetujuan Ketua/Wakil (selain kontennya sendiri).
 */
class ContentQueueWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Antrean konten';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active
            && ($user->isSuperAdmin() || PostPolicy::isCoordinatorApprover($user));
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $url = PostResource::getUrl('index');
        $notMine = fn () => Post::query()->where('author_id', '!=', $user->getKey());

        if ($user->isSuperAdmin()) {
            $pending = $notMine()->where('status', PostStatus::PendingLeader->value)->count();

            return [
                Stat::make('Menunggu persetujuan Ketua/Wakil', $pending)
                    ->icon(Heroicon::OutlinedCheckBadge)->color($pending ? 'warning' : 'success')->url($url),
                Stat::make('Menunggu Koordinator Medpub', Post::query()->where('status', PostStatus::PendingCoordinator->value)->count())
                    ->icon(Heroicon::OutlinedClock)->color('gray')->url($url),
                Stat::make('Siap terbit', Post::query()->where('status', PostStatus::Approved->value)->count())
                    ->icon(Heroicon::OutlinedGlobeAlt)->color('info')->url($url),
            ];
        }

        $pending = $notMine()->where('status', PostStatus::PendingCoordinator->value)->count();
        $approved = Post::query()->where('status', PostStatus::Approved->value)->count();
        $notPosted = Post::query()->where('status', PostStatus::Published->value)->whereNull('social_posted_at')->count();

        return [
            Stat::make('Menunggu persetujuan saya', $pending)
                ->icon(Heroicon::OutlinedInboxArrowDown)->color($pending ? 'warning' : 'success')->url($url),
            Stat::make('Siap diterbitkan', $approved)
                ->icon(Heroicon::OutlinedGlobeAlt)->color($approved ? 'info' : 'gray')->url($url),
            Stat::make('Terbit, belum diposting ke medsos', $notPosted)
                ->icon(Heroicon::OutlinedShare)->color($notPosted ? 'danger' : 'success')->url($url),
        ];
    }
}
