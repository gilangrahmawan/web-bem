<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AnnouncementType;
use App\Enums\RoleName;
use App\Filament\Resources\Announcements\RelationManagers\RecipientsRelationManager;
use App\Models\Announcement;
use App\Models\Division;
use App\Models\User;
use App\Support\Format;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Permission\Models\Role;

/**
 * Basis resource Pengumuman (Sekretaris) & Surat (Humas). Dikirim langsung saat disimpan lewat
 * AnnouncementService; tidak bisa diedit; hapus hanya super_admin. Dikecualikan dari Shield.
 */
abstract class AnnouncementResourceBase extends Resource
{
    protected static ?string $model = Announcement::class;

    /** Lampiran: PDF/DOCX/XLSX/JPG/PNG ≤ 10 MB (AGENTS.md B4). */
    public const ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
    ];

    abstract public static function type(): AnnouncementType;

    public static function canCreate(): bool
    {
        return (bool) self::user()?->can('createType', [Announcement::class, static::type()]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** @return Builder<Announcement> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->ofType(static::type())
            ->with('creator')
            ->withCount([
                'recipients',
                'recipients as read_count' => fn (Builder $q) => $q->whereNotNull('read_at'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        $isLetter = static::type() === AnnouncementType::Letter;

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->label('Judul')->required()->maxLength(200),
                    Textarea::make('body')->label('Isi')->required()->rows(8)->maxLength(10000),
                    FileUpload::make('attachment_path')
                        ->label('Lampiran (opsional)')
                        ->disk(Announcement::DISK)
                        ->directory(Announcement::DIRECTORY)
                        ->visibility('private')
                        ->maxSize(10240)
                        ->acceptedFileTypes(self::ATTACHMENT_MIME_TYPES)
                        ->rules(['mimes:pdf,docx,xlsx,jpg,jpeg,png'])
                        ->storeFileNamesIn('attachment_name')
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin')
                        )
                        ->helperText('PDF, DOCX, XLSX, JPG, atau PNG, maksimal 10 MB. Disimpan privat.'),
                ]),
            Section::make('Penerima')
                ->description($isLetter
                    ? 'Penerima dapat membalas atau menandai "sudah ditindaklanjuti".'
                    : 'Pengumuman langsung terkirim ke lonceng & Kotak Masuk penerima dan tidak bisa diedit setelahnya.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('target_all')->label('Semua pengurus (termasuk Pembina)')->default(true)->live()->columnSpanFull(),
                    Select::make('roles')->label('Role')->multiple()
                        ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name')->map(fn (string $n) => RoleName::labelFor($n)))
                        ->hidden(fn (Get $get): bool => (bool) $get('target_all')),
                    Select::make('divisions')->label('Divisi')->multiple()
                        ->options(fn () => Division::query()->orderBy('name')->pluck('name', 'id'))
                        ->hidden(fn (Get $get): bool => (bool) $get('target_all')),
                    Select::make('users')->label('Pengurus tertentu')->multiple()->searchable()
                        ->options(fn () => User::query()->active()->whereHas('roles')->orderBy('name')->pluck('name', 'id'))
                        ->hidden(fn (Get $get): bool => (bool) $get('target_all')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('creator.name')->label('Pengirim')->placeholder('-'),
                TextColumn::make('read_count')->label('Dibaca')
                    ->state(fn (Announcement $record): string => "{$record->read_count}/{$record->recipients_count}"),
                TextColumn::make('created_at')->label('Dikirim')
                    ->formatStateUsing(fn (Announcement $record): string => Format::date($record->created_at))->sortable(),
            ])
            ->recordUrl(fn (Announcement $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada '.mb_strtolower(static::type()->label()));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('title')->label('Judul')->columnSpanFull()->weight('bold'),
                    TextEntry::make('creator.name')->label('Pengirim')->placeholder('-'),
                    TextEntry::make('created_at')->label('Dikirim')
                        ->formatStateUsing(fn (Announcement $record): string => Format::date($record->created_at).', '.$record->created_at->format('H.i')),
                    TextEntry::make('read_count')->label('Dibaca')
                        ->state(fn (Announcement $record): string => $record->recipients()->whereNotNull('read_at')->count().'/'.$record->recipients()->count().' penerima'),
                    TextEntry::make('body')->label('Isi')->columnSpanFull()->extraAttributes(['style' => 'white-space: pre-line']),
                    TextEntry::make('attachment_name')->label('Lampiran')->placeholder('Tidak ada')
                        ->formatStateUsing(fn (?string $state, Announcement $record): HtmlString => new HtmlString(
                            '<a href="'.e(route('panel.announcements.attachment', $record)).'" style="text-decoration: underline;">'.e((string) $state).'</a>'
                        )),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [RecipientsRelationManager::class];
    }

    protected static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
