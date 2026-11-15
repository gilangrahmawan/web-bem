<?php

namespace App\Filament\Resources\Downloads\Tables;

use App\Enums\DownloadCategory;
use App\Models\Download;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DownloadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->sortable()
                    ->description(fn (Download $record): string => $record->original_name),
                TextColumn::make('category')->label('Kategori')->badge()
                    ->formatStateUsing(fn (DownloadCategory $state): string => $state->label())
                    ->color(fn (DownloadCategory $state): string => $state->color()),
                TextColumn::make('size')->label('Ukuran')
                    ->formatStateUsing(fn (Download $record): string => $record->humanSize()),
                TextColumn::make('download_count')->label('Diunduh')->numeric()->sortable(),
                IconColumn::make('is_active')->label('Publik')->boolean(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategori')
                    ->options(collect(DownloadCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                TernaryFilter::make('is_active')->label('Status')->trueLabel('Publik')->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Unduh')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Download $record): bool => Gate::allows('view', $record))
                    ->action(function (Download $record) {
                        Gate::authorize('view', $record);

                        return Storage::disk(Download::DISK)->download($record->file_path, $record->original_name);
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Berkas akan dihapus permanen dari server.'),
            ]);
    }
}
