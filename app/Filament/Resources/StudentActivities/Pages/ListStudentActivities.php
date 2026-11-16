<?php

namespace App\Filament\Resources\StudentActivities\Pages;

use App\Enums\ActivityStatus;
use App\Filament\Resources\StudentActivities\StudentActivityPolicyHelper;
use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Models\User;
use App\Policies\StudentActivityPolicy;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Tab BEM: Menunggu Saya, per tahap, Berjalan, LPJ Terlambat, Selesai, Semua. Pengaju: Aktif & Semua. */
class ListStudentActivities extends ListRecords
{
    protected static string $resource = StudentActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat kegiatan')];
    }

    public function getTabs(): array
    {
        $user = $this->user();
        $in = fn (ActivityStatus ...$s) => fn (Builder $query) => $query->whereIn('status', array_map(fn ($x) => $x->value, $s));
        $waiting = StudentActivityPolicyHelper::statusesFor($user);
        $seesAll = StudentActivityPolicy::seesAll($user);

        return [
            'waiting' => Tab::make('Menunggu Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $waiting)->where('pic_user_id', '!=', $user->getKey()))
                ->visible($waiting !== []),
            'secretary' => Tab::make('Tahap Sekretaris')->modifyQueryUsing($in(ActivityStatus::ProposalSubmitted, ActivityStatus::LpjSubmitted))->visible($seesAll),
            'treasurer' => Tab::make('Tahap Bendahara')->modifyQueryUsing($in(ActivityStatus::ProposalVerified, ActivityStatus::LpjVerified))->visible($seesAll),
            'leader' => Tab::make('Tahap Ketua/Wakil')->modifyQueryUsing($in(ActivityStatus::ProposalBudgetReviewed, ActivityStatus::LpjBudgetReviewed))->visible($seesAll),
            'running' => Tab::make('Berjalan / menunggu LPJ')->modifyQueryUsing($in(ActivityStatus::Approved, ActivityStatus::LpjRevision)),
            'overdue' => Tab::make('LPJ Terlambat')->modifyQueryUsing(fn (Builder $query) => $query->lpjOverdue()),
            'done' => Tab::make('Selesai')->modifyQueryUsing($in(ActivityStatus::Completed)),
            'all' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return StudentActivityPolicyHelper::statusesFor($this->user()) !== [] ? 'waiting' : 'all';
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
