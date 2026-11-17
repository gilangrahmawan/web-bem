<?php

namespace App\Filament\Resources\Forms;

use App\Enums\FormStatus;
use App\Filament\Resources\Forms\Pages\FormBuilder;
use App\Filament\Resources\Forms\Pages\ListForms;
use App\Models\Division;
use App\Models\Form;
use App\Models\User;
use App\Policies\FormPolicy;
use App\Services\Forms\FormBuilderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Daftar form & survei (Fase 11A). Super Admin & Sekretaris: semua form; koordinator: form divisinya;
 * Pembina: lihat saja. Dijaga FormPolicy (dikecualikan dari permission resource Shield).
 */
class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'form';

    protected static ?string $pluralModelLabel = 'form';

    protected static ?string $navigationLabel = 'Form & Survei';

    protected static ?string $slug = 'form';

    public static function getEloquentQuery(): Builder
    {
        $user = self::user();

        return parent::getEloquentQuery()->when(
            $user,
            fn (Builder $query) => $query->visibleTo($user),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );
    }

    /** Kolom isian modal "Buat form". */
    public static function createSchema(): array
    {
        return [
            TextInput::make('title')->label('Judul form')->required()->maxLength(200),
            Textarea::make('description')->label('Deskripsi (opsional)')->rows(3)->maxLength(3000),
            Select::make('division_id')->label('Divisi pemilik')
                ->options(fn () => Division::query()->orderBy('name')->pluck('name', 'id'))
                ->placeholder('BEM (umum)')
                ->visible(fn (): bool => self::user() !== null && FormPolicy::managesAll(self::user()))
                ->helperText('Koordinator divisi bisa mengelola form milik divisinya.'),
            Toggle::make('is_quiz')->label('Jadikan kuis (dengan nilai)'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('division')
                ->withCount(['responses as submitted_count' => fn (Builder $query) => $query->whereNotNull('submitted_at')]))
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Form $record): string => FormBuilder::getUrl(['record' => $record]))
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->weight('semibold')->wrap()
                    ->description(fn (Form $r): ?string => $r->is_quiz ? 'Kuis' : null),
                TextColumn::make('division.name')->label('Divisi')->placeholder('BEM (umum)'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (FormStatus $state): string => $state->label())
                    ->color(fn (FormStatus $state): string => $state->color()),
                TextColumn::make('submitted_count')->label('Jawaban')->numeric(),
                TextColumn::make('updated_at')->label('Diubah')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')
                    ->options(collect(FormStatus::cases())->mapWithKeys(fn (FormStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (Form $r): string => (self::user()?->can('update', $r) ?? false) ? 'Edit' : 'Lihat')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Form $r): string => FormBuilder::getUrl(['record' => $r])),
                Action::make('duplicate')->label('Gandakan')->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
                    ->visible(fn (Form $r): bool => (self::user()?->can('create', Form::class) ?? false) && (self::user()?->can('view', $r) ?? false))
                    ->action(function (Form $record) {
                        abort_unless(self::user()?->can('create', Form::class) && self::user()->can('view', $record), 403);
                        $copy = app(FormBuilderService::class)->duplicate($record, self::user());
                        Notification::make()->title('Form digandakan')->success()->send();

                        return redirect(FormBuilder::getUrl(['record' => $copy]));
                    }),
                Action::make('delete')->label('Hapus')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn (Form $r): bool => self::user()?->can('delete', $r) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Hapus form?')
                    ->modalDescription('Form yang belum punya jawaban akan dihapus. Tindakan ini tidak bisa dibatalkan.')
                    ->action(function (Form $record): void {
                        abort_unless(self::user()?->can('delete', $record), 403);
                        app(FormBuilderService::class)->delete($record, self::user());
                        Notification::make()->title('Form dihapus')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada form')
            ->emptyStateDescription('Buat form untuk survei, kuisioner, atau pendaftaran.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForms::route('/'),
            'builder' => FormBuilder::route('/{record}/pembuat'),
        ];
    }

    public static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
