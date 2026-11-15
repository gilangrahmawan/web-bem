<?php

namespace App\Filament\Resources\Aspirations\Pages;

use App\Enums\AspirationStatus;
use App\Filament\Resources\Aspirations\AspirationResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Tab per peran: Inbox Humas, Eskalasi (Ketua/Wakil), Perlu Dikaji (Kajian), Berjalan, Selesai, Semua. */
class ListAspirations extends ListRecords
{
    protected static string $resource = AspirationResource::class;

    public function getTabs(): array
    {
        $user = $this->user();
        $statuses = fn (AspirationStatus ...$s) => fn (Builder $query) => $query->whereIn('status', array_map(fn ($x) => $x->value, $s));

        return [
            'inbox' => Tab::make('Inbox Humas')
                ->modifyQueryUsing($statuses(AspirationStatus::New, AspirationStatus::InReview, AspirationStatus::Returned))
                ->visible($user->can('aspiration.handle')),
            'escalated' => Tab::make('Eskalasi')
                ->modifyQueryUsing($statuses(AspirationStatus::Escalated))
                ->visible($user->can('aspiration.forward_to_kajian')),
            'study' => Tab::make('Perlu Dikaji')
                ->modifyQueryUsing($statuses(AspirationStatus::InStudy))
                ->visible($user->can('aspiration.study') || $user->division?->slug === 'kajian'),
            'open' => Tab::make('Berjalan')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', [AspirationStatus::Resolved->value, AspirationStatus::Rejected->value])),
            'closed' => Tab::make('Selesai/Ditolak')
                ->modifyQueryUsing($statuses(AspirationStatus::Resolved, AspirationStatus::Rejected)),
            'all' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $user = $this->user();

        return match (true) {
            $user->can('aspiration.handle') => 'inbox',
            $user->can('aspiration.forward_to_kajian') => 'escalated',
            $user->can('aspiration.study') || $user->division?->slug === 'kajian' => 'study',
            default => 'open',
        };
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
