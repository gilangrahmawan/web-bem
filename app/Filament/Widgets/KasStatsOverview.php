<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ViewsAllKas;
use App\Support\Format;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Kartu ringkasan kas: terkumpul, tunggakan, pengurus lunas. */
class KasStatsOverview extends StatsOverviewWidget
{
    use ViewsAllKas;

    protected static ?int $sort = 10;

    protected ?string $heading = 'Ringkasan kas mingguan';

    protected function getDescription(): ?string
    {
        return $this->kas()->setupProblem() ?? $this->kas()->period()?->name;
    }

    protected function getStats(): array
    {
        $totals = $this->kas()->totals();
        $ready = $this->kas()->setupProblem() === null;

        return [
            Stat::make('Total kas terkumpul', Format::rupiah($totals['collected']))
                ->description('Periode aktif')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Total tunggakan', $ready ? Format::rupiah($totals['arrears']) : '—')
                ->description($ready ? "{$totals['arrears_people']} pengurus menunggak" : 'Tanggal mulai kas belum diatur')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($totals['arrears'] > 0 ? 'danger' : 'success'),
            Stat::make('Pengurus lunas', $ready ? "{$totals['clear']}/{$totals['payers']}" : '—')
                ->description($ready && $totals['payers'] > 0
                    ? round($totals['clear'] / $totals['payers'] * 100).'% pengurus wajib kas'
                    : 'Belum ada data')
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color('success'),
        ];
    }
}
