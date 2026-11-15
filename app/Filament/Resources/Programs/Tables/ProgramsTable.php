<?php

namespace App\Filament\Resources\Programs\Tables;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\Period;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('title')->label('Program')->searchable()->sortable()->wrap(),
                TextColumn::make('division.name')->label('Divisi')->badge()->color('gray'),
                TextColumn::make('period.name')->label('Periode')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (ProgramStatus $state): string => $state->label())
                    ->color(fn (ProgramStatus $state): string => $state->color()),
                TextColumn::make('starts_on')->label('Mulai')->date('d F Y')->sortable()->placeholder('-'),
                TextColumn::make('ends_on')->label('Selesai')->date('d F Y')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('period_id')
                    ->label('Periode')
                    ->relationship('period', 'name')
                    ->default(fn (): ?int => Period::current()?->id),
                SelectFilter::make('division_id')
                    ->label('Divisi')
                    ->relationship('division', 'name')
                    ->visible(fn (): bool => ProgramResource::restrictedDivisionId() === null),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ProgramStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
