<?php

namespace App\Filament\Resources\Aspirations;

use App\Enums\AspirationCategory;
use App\Enums\AspirationStatus;
use App\Filament\Resources\Aspirations\Pages\ListAspirations;
use App\Filament\Resources\Aspirations\Pages\ViewAspiration;
use App\Filament\Resources\Discussions\DiscussionResource;
use App\Models\Aspiration;
use App\Models\AspirationLog;
use App\Models\Discussion;
use App\Models\User;
use App\Policies\AspirationPolicy;
use App\Support\Format;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Aspirasi (Humas). Daftar dibatasi Aspiration::visibleTo(); identitas pengirim hanya untuk
 * Humas & super_admin. Semua aksi lewat AspirationService (di ViewAspiration).
 */
class AspirationResource extends Resource
{
    protected static ?string $model = Aspiration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Humas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'aspirasi';

    protected static ?string $pluralModelLabel = 'aspirasi';

    protected static ?string $navigationLabel = 'Aspirasi';

    protected static ?string $slug = 'aspirasi';

    protected static ?string $recordTitleAttribute = 'tracking_code';

    public static function getNavigationBadge(): ?string
    {
        $user = self::user();

        if (! $user || ! $user->can('aspiration.handle')) {
            return null;
        }

        $count = Aspiration::query()->visibleTo($user)
            ->whereIn('status', [AspirationStatus::New->value, AspirationStatus::Returned->value])->count();

        return $count > 0 ? (string) $count : null;
    }

    /** @return Builder<Aspiration> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = self::user();

        return $user ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function canCreate(): bool
    {
        return false; // Aspirasi hanya masuk dari form publik.
    }

    public static function table(Table $table): Table
    {
        $seesIdentity = ($user = self::user()) && AspirationPolicy::seesIdentity($user);

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('tracking_code')->label('Kode')->searchable()->copyable()->fontFamily('mono')->weight('semibold'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (AspirationStatus $state): string => $state->label())
                    ->color(fn (AspirationStatus $state): string => $state->color()),
                TextColumn::make('category')->label('Kategori')
                    ->formatStateUsing(fn (AspirationCategory $state): string => $state->label()),
                TextColumn::make('body')->label('Isi')->limit(80)->wrap()->searchable(),
                TextColumn::make('name')->label('Pengirim')
                    ->state(fn (Aspiration $record): string => $record->is_anonymous ? 'Anonim' : ($record->name ?: 'Tanpa nama'))
                    ->visible($seesIdentity),
                TextColumn::make('created_at')->label('Masuk')
                    ->formatStateUsing(fn (Aspiration $record): string => Format::date($record->created_at))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(AspirationStatus::options()),
                SelectFilter::make('category')->label('Kategori')->options(AspirationCategory::options()),
            ])
            ->recordUrl(fn (Aspiration $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada aspirasi')
            ->emptyStateIcon(Heroicon::OutlinedMegaphone);
    }

    public static function infolist(Schema $schema): Schema
    {
        $seesIdentity = ($user = self::user()) && AspirationPolicy::seesIdentity($user);

        return $schema->components([
            Section::make('Aspirasi')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('tracking_code')->label('Kode')->copyable()->fontFamily('mono'),
                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn (AspirationStatus $state): string => $state->label())
                        ->color(fn (AspirationStatus $state): string => $state->color()),
                    TextEntry::make('category')->label('Kategori')->formatStateUsing(fn (AspirationCategory $state): string => $state->label()),
                    TextEntry::make('created_at')->label('Masuk')->formatStateUsing(fn (Aspiration $record): string => Format::date($record->created_at).', '.$record->created_at->format('H.i')),
                    TextEntry::make('handler.name')->label('Ditangani')->placeholder('-'),
                    TextEntry::make('body')->label('Isi aspirasi')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
                ]),
            Section::make('Pengirim')
                ->description('Hanya terlihat oleh Humas dan Ketua/Wakil.')
                ->columns(2)
                ->columnSpanFull()
                ->visible($seesIdentity)
                ->schema([
                    TextEntry::make('name')->label('Nama')
                        ->state(fn (Aspiration $record): string => $record->is_anonymous ? 'Anonim (identitas & IP tidak disimpan)' : ($record->name ?: '-')),
                    TextEntry::make('contact')->label('Kontak')->placeholder('-')
                        ->visible(fn (Aspiration $record): bool => ! $record->is_anonymous),
                ]),
            Section::make('Untuk pengirim (tampil di cek status)')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('response')->label('Tanggapan')->placeholder('Belum ada tanggapan')->extraAttributes(['style' => 'white-space: pre-line']),
                    TextEntry::make('rejection_reason')->label('Alasan penolakan')
                        ->visible(fn (Aspiration $record): bool => filled($record->rejection_reason))
                        ->extraAttributes(['style' => 'white-space: pre-line']),
                ]),
            Section::make('Pembahasan Kajian tertaut')
                ->columnSpanFull()
                ->visible(fn (Aspiration $record): bool => $record->discussions()->exists())
                ->schema([
                    RepeatableEntry::make('discussions')->hiddenLabel()->schema([
                        TextEntry::make('title')->hiddenLabel()
                            ->url(fn (Discussion $record): string => DiscussionResource::getUrl('edit', ['record' => $record])),
                    ]),
                ]),
            Section::make('Riwayat & catatan internal')
                ->description('Catatan di sini TIDAK ditampilkan ke pengirim.')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    RepeatableEntry::make('logs')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('to_status')->label('Status')->badge()
                            ->formatStateUsing(fn (AspirationStatus $state): string => $state->label())
                            ->color(fn (AspirationStatus $state): string => $state->color()),
                        TextEntry::make('user.name')->label('Oleh')->placeholder('Form publik'),
                        TextEntry::make('created_at')->label('Waktu')
                            ->formatStateUsing(fn (AspirationLog $record): string => Format::date($record->created_at).', '.$record->created_at->format('H.i')),
                        TextEntry::make('note')->label('Catatan internal')->placeholder('-')->columnSpanFull()
                            ->formatStateUsing(fn (?string $state): string => Str::limit((string) $state, 2000)),
                    ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAspirations::route('/'),
            'view' => ViewAspiration::route('/{record}'),
        ];
    }

    private static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
