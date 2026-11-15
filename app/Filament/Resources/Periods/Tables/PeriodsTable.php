<?php

namespace App\Filament\Resources\Periods\Tables;

use App\Models\Period;
use App\Services\PeriodService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama kabinet')->searchable()->sortable(),
                TextColumn::make('starts_on')->label('Mulai')->date('d F Y')->sortable(),
                TextColumn::make('ends_on')->label('Selesai')->date('d F Y')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Jadikan aktif')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Periode lain yang sedang aktif akan dinonaktifkan.')
                    ->visible(fn (Period $record): bool => Gate::allows('activate', $record))
                    ->action(function (Period $record): void {
                        Gate::authorize('activate', $record);
                        app(PeriodService::class)->activate($record);

                        Notification::make()->title('Periode diaktifkan')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
