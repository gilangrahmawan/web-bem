<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Filament\Widgets\Concerns\ViewsAllActivities;
use App\Models\StudentActivity;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Daftar LPJ terlambat (badge merah) — BEM. Belum ada sanksi (lihat LpjSanctionPolicy). */
class OverdueLpjWidget extends TableWidget
{
    use ViewsAllActivities;

    protected static ?int $sort = 41;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'LPJ terlambat';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => StudentActivity::query()->lpjOverdue()->with(['organization', 'pic']))
            ->defaultSort('ends_on')
            ->columns([
                TextColumn::make('name')->label('Kegiatan')->weight('semibold')
                    ->description(fn (StudentActivity $r): ?string => $r->organization?->name),
                TextColumn::make('pic.name')->label('Penanggung jawab'),
                TextColumn::make('due')->label('Tenggat')->badge()->color('danger')
                    ->state(fn (StudentActivity $r): string => Format::date($r->lpjDueOn()).' · terlambat '.(int) $r->lpjDueOn()->diffInDays(now()->startOfDay()).' hari'),
            ])
            ->recordUrl(fn (StudentActivity $r): string => StudentActivityResource::getUrl('view', ['record' => $r]))
            ->paginated([5, 10])
            ->emptyStateHeading('Tidak ada LPJ terlambat')
            ->emptyStateDescription('Semua LPJ masih dalam tenggat atau sudah masuk.');
    }
}
