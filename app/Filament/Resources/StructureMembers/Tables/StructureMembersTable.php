<?php

namespace App\Filament\Resources\StructureMembers\Tables;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\Period;
use App\Models\StructureMember;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StructureMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular()
                    ->defaultImageUrl(fn (StructureMember $record): string => InitialsAvatarProvider::forName($record->name)),
                TextColumn::make('name')->label('Nama')->searchable()
                    ->description(fn (StructureMember $record): ?string => $record->prodi),
                TextColumn::make('position')->label('Jabatan'),
                TextColumn::make('division.name')->label('Divisi')->badge()->color('gray')->placeholder('Pimpinan'),
                TextColumn::make('period.name')->label('Periode')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('period_id')
                    ->label('Periode')
                    ->relationship('period', 'name')
                    ->default(fn (): ?int => Period::current()?->id),
                SelectFilter::make('division_id')->label('Divisi')->relationship('division', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
