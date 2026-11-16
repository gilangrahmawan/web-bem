<?php

namespace App\Filament\Widgets;

use App\Enums\ActivityStatus;
use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Filament\Widgets\Concerns\ViewsAllActivities;
use App\Models\StudentActivity;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Antrean review per tahap, LPJ terlambat, dan rata-rata lama proses — BEM. */
class ActivityQueueWidget extends StatsOverviewWidget
{
    use ViewsAllActivities;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Kegiatan: antrean & lama proses';

    protected function getColumns(): int|array|null
    {
        return ['default' => 2, 'lg' => 5];
    }

    protected function getStats(): array
    {
        $count = fn (ActivityStatus ...$s) => StudentActivity::query()->whereIn('status', array_map(fn ($x) => $x->value, $s))->count();
        $url = StudentActivityResource::getUrl('index');
        $overdue = StudentActivity::query()->lpjOverdue()->count();

        return [
            Stat::make('Tahap Sekretaris', $count(ActivityStatus::ProposalSubmitted, ActivityStatus::LpjSubmitted))->icon(Heroicon::OutlinedClipboardDocumentList)->url($url),
            Stat::make('Tahap Bendahara', $count(ActivityStatus::ProposalVerified, ActivityStatus::LpjVerified))->icon(Heroicon::OutlinedBanknotes)->url($url),
            Stat::make('Tahap Ketua/Wakil', $count(ActivityStatus::ProposalBudgetReviewed, ActivityStatus::LpjBudgetReviewed))->icon(Heroicon::OutlinedCheckBadge)->url($url),
            Stat::make('LPJ terlambat', $overdue)->icon(Heroicon::OutlinedExclamationTriangle)->color($overdue ? 'danger' : 'success')->url($url),
            Stat::make('Rata-rata proses proposal', $this->averageDays('proposal_submitted_at', 'proposal_approved_at'))
                ->description('LPJ: '.$this->averageDays('lpj_submitted_at', 'completed_at'))
                ->icon(Heroicon::OutlinedClock),
        ];
    }

    /** Rata-rata hari dari kolom awal ke kolom akhir (kegiatan yang sudah sampai tahap akhir). */
    private function averageDays(string $from, string $to): string
    {
        $rows = StudentActivity::query()->whereNotNull($from)->whereNotNull($to)->get([$from, $to]);

        if ($rows->isEmpty()) {
            return 'Belum ada data';
        }

        $avg = $rows->avg(fn (StudentActivity $a) => CarbonImmutable::parse($a->{$from})->diffInHours(CarbonImmutable::parse($a->{$to})) / 24);

        return number_format((float) $avg, 1, ',', '.').' hari';
    }
}
