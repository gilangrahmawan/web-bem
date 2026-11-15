<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use App\Models\ActivityLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('created_at')->label('Waktu')->dateTime('d F Y H:i:s'),
                        TextEntry::make('action')
                            ->label('Aksi')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ActivityLog::actionLabel($state)),
                        TextEntry::make('user.name')->label('Pelaku')->placeholder('Sistem / tamu'),
                        TextEntry::make('ip')->label('IP')->placeholder('-'),
                        TextEntry::make('subject_type')
                            ->label('Objek')
                            ->formatStateUsing(fn (?string $state, ActivityLog $record): string => $state
                                ? class_basename($state).' #'.$record->subject_id
                                : '-')
                            ->placeholder('-'),
                        KeyValueEntry::make('properties')
                            ->label('Detail')
                            ->getStateUsing(fn (ActivityLog $record): array => collect($record->properties ?? [])
                                ->map(fn ($value) => is_scalar($value) || $value === null
                                    ? (string) $value
                                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                                ->all())
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
