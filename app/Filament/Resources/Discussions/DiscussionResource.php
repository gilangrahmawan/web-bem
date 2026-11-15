<?php

namespace App\Filament\Resources\Discussions;

use App\Enums\DiscussionStatus;
use App\Filament\Resources\Discussions\Pages\CreateDiscussion;
use App\Filament\Resources\Discussions\Pages\EditDiscussion;
use App\Filament\Resources\Discussions\Pages\ListDiscussions;
use App\Filament\Resources\Discussions\Schemas\DiscussionForm;
use App\Models\Discussion;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Tabel pembahasan Divisi Kajian (AGENTS.md C3). Dijaga DiscussionPolicy; dikecualikan dari Shield. */
class DiscussionResource extends Resource
{
    protected static ?string $model = Discussion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Kajian';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pembahasan';

    protected static ?string $pluralModelLabel = 'pembahasan';

    protected static ?string $navigationLabel = 'Pembahasan Kajian';

    protected static ?string $slug = 'pembahasan';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DiscussionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['creator', 'aspiration']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (DiscussionStatus $state): string => $state->label())
                    ->color(fn (DiscussionStatus $state): string => $state->color()),
                TextColumn::make('aspiration.tracking_code')->label('Aspirasi')->placeholder('-')->fontFamily('mono'),
                TextColumn::make('creator.name')->label('Penulis')->placeholder('-'),
                TextColumn::make('updated_at')->label('Diperbarui')
                    ->formatStateUsing(fn (Discussion $record): string => Format::date($record->updated_at))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(DiscussionStatus::options()),
                Filter::make('mine')->label('Milik saya')->toggle()
                    ->query(fn (Builder $query) => $query->where('created_by', Filament::auth()->id())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Belum ada pembahasan')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleBottomCenterText);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscussions::route('/'),
            'create' => CreateDiscussion::route('/create'),
            'edit' => EditDiscussion::route('/{record}/edit'),
        ];
    }
}
