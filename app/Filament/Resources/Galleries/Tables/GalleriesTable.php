<?php

namespace App\Filament\Resources\Galleries\Tables;

use App\Models\Gallery;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GalleriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('coverPhoto')->withCount('photos'))
            ->defaultSort('event_date', 'desc')
            ->columns([
                ImageColumn::make('coverPhoto.thumb_path')->label('')->disk('public')->square(),
                TextColumn::make('title')->label('Album')->searchable()->sortable(),
                TextColumn::make('event_date')->label('Tanggal')->date('d F Y')->sortable()->placeholder('-'),
                TextColumn::make('photos_count')->label('Foto')->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(fn (Gallery $record): string => "Album beserta {$record->photos_count} foto akan dihapus permanen."),
            ]);
    }
}
