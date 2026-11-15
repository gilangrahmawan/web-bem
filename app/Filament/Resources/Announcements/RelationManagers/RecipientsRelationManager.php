<?php

namespace App\Filament\Resources\Announcements\RelationManagers;

use App\Enums\AnnouncementType;
use App\Models\AnnouncementRecipient;
use App\Support\Format;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Daftar penerima: siapa sudah membaca, ditindaklanjuti, dan balasannya (read-only). */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Penerima & status baca';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $isLetter = $this->getOwnerRecord()->type === AnnouncementType::Letter;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user.division'))
            ->defaultSort('read_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Penerima')->searchable()
                    ->description(fn (AnnouncementRecipient $r): ?string => $r->user?->division?->name),
                IconColumn::make('read_at')->label('Dibaca')->boolean()
                    ->getStateUsing(fn (AnnouncementRecipient $r): bool => $r->read_at !== null)
                    ->tooltip(fn (AnnouncementRecipient $r): ?string => $r->read_at ? Format::date($r->read_at).', '.$r->read_at->format('H.i') : null),
                IconColumn::make('followed_up_at')->label('Ditindaklanjuti')->boolean()->visible($isLetter)
                    ->getStateUsing(fn (AnnouncementRecipient $r): bool => $r->followed_up_at !== null),
                TextColumn::make('reply')->label('Balasan')->placeholder('-')->wrap()->limit(200)->visible($isLetter),
            ])
            ->filters([
                TernaryFilter::make('read')->label('Status baca')
                    ->trueLabel('Sudah dibaca')->falseLabel('Belum dibaca')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('read_at'),
                        false: fn (Builder $query) => $query->whereNull('read_at'),
                    ),
            ]);
    }
}
