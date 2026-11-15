<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Tab: Draft Saya, Menunggu Saya, Disetujui, Terbit, Semua (AGENTS.md Fase 6). */
class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat konten')];
    }

    public function getTabs(): array
    {
        $user = $this->user();
        $waiting = array_filter([
            PostPolicy::isCoordinatorApprover($user) ? PostStatus::PendingCoordinator->value : null,
            $user->isSuperAdmin() ? PostStatus::PendingLeader->value : null,
        ]);

        return [
            'mine' => Tab::make('Draft Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('author_id', $user->getKey())
                    ->whereIn('status', [PostStatus::Draft->value, PostStatus::Revision->value]))
                ->visible($user->can('create', Post::class)),
            'waiting' => Tab::make('Menunggu Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $waiting)->where('author_id', '!=', $user->getKey()))
                ->visible($waiting !== []),
            'approved' => Tab::make('Disetujui')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PostStatus::Approved->value)),
            'published' => Tab::make('Terbit')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PostStatus::Published->value)),
            'all' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $user = $this->user();

        return match (true) {
            PostPolicy::isCoordinatorApprover($user), $user->isSuperAdmin() => 'waiting',
            $user->can('create', Post::class) => 'mine',
            default => 'published',
        };
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
