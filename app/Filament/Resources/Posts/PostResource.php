<?php

namespace App\Filament\Resources\Posts;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Filament\Support\PostCoverUpload;
use App\Models\Post;
use App\Models\PostApproval;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Support\Format;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Konten website (Medpub). Draft lewat PostContentService, alur lewat PostWorkflowService.
 * Dijaga PostPolicy; dikecualikan dari permission resource Shield.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Medpub';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'konten';

    protected static ?string $pluralModelLabel = 'konten';

    protected static ?string $navigationLabel = 'Konten';

    protected static ?string $slug = 'konten';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $user = self::user();

        if (! $user) {
            return null;
        }

        $statuses = array_filter([
            PostPolicy::isCoordinatorApprover($user) ? PostStatus::PendingCoordinator->value : null,
            $user->isSuperAdmin() ? PostStatus::PendingLeader->value : null,
        ]);

        if ($statuses === []) {
            return null;
        }

        $count = Post::query()->whereIn('status', $statuses)->where('author_id', '!=', $user->getKey())->count();

        return $count > 0 ? (string) $count : null;
    }

    /** @return Builder<Post> */
    public static function getEloquentQuery(): Builder
    {
        $user = self::user();

        return parent::getEloquentQuery()->with('author')
            ->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function form(Schema $schema): Schema
    {
        $types = ($user = self::user()) ? PostPolicy::allowedTypes($user) : [];
        $isNews = fn (Get $get): bool => in_array($get('type'), PostType::newsTypes(), true);

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('type')->label('Tipe konten')
                        ->options(collect($types)->mapWithKeys(fn (PostType $t) => [$t->value => $t->label()]))
                        ->default(count($types) === 1 ? $types[0]->value : null)
                        ->required()
                        ->live(),
                    Select::make('category')->label('Kategori berita')
                        ->options(collect(PostCategory::cases())->mapWithKeys(fn (PostCategory $c) => [$c->value => $c->label()]))
                        ->visible($isNews),
                    TextInput::make('title')->label('Judul')->required()->maxLength(200)->columnSpanFull(),
                    Textarea::make('excerpt')->label('Ringkasan (untuk daftar & SEO)')->rows(2)->maxLength(500)->columnSpanFull(),
                    PostCoverUpload::make()->columnSpanFull(),
                    RichEditor::make('body')->label('Isi')
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'strike', 'link'],
                            ['h2', 'h3', 'blockquote', 'bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->columnSpanFull(),
                    Textarea::make('social_caption')->label('Caption medsos')
                        ->helperText('Terpisah dari isi website. Wajib untuk tipe Postingan.')
                        ->rows(5)->maxLength(2200)->columnSpanFull(),
                    FileUpload::make('attachment_path')->label('Lampiran PDF (opsional)')
                        ->disk('public')->directory('posts/attachments')->visibility('public')
                        ->maxSize(10240)
                        ->acceptedFileTypes(['application/pdf'])
                        ->rules(['mimes:pdf'])
                        ->storeFileNamesIn('attachment_name')
                        ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.pdf')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('type')->label('Tipe')->badge()
                    ->formatStateUsing(fn (PostType $state): string => $state->label())
                    ->color(fn (PostType $state): string => $state->color()),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (PostStatus $state): string => $state->label())
                    ->color(fn (PostStatus $state): string => $state->color()),
                TextColumn::make('author.name')->label('Penulis'),
                TextColumn::make('updated_at')->label('Diperbarui')
                    ->formatStateUsing(fn (Post $record): string => Format::date($record->updated_at))->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipe')->options(collect(PostType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                SelectFilter::make('status')->label('Status')->options(collect(PostStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordUrl(fn (Post $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada konten')
            ->emptyStateIcon(Heroicon::OutlinedNewspaper);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('title')->label('Judul')->weight('bold')->columnSpanFull(),
                    TextEntry::make('type')->label('Tipe')->badge()->formatStateUsing(fn (PostType $state): string => $state->label()),
                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn (PostStatus $state): string => $state->label())
                        ->color(fn (PostStatus $state): string => $state->color()),
                    TextEntry::make('author.name')->label('Penulis'),
                    TextEntry::make('published_at')->label('Terbit')->placeholder('-')
                        ->formatStateUsing(fn (Post $record): string => Format::date($record->published_at)),
                    TextEntry::make('public_url')->label('Halaman publik')
                        ->state(fn (Post $record): ?string => $record->isPublished() ? $record->publicUrl() : null)
                        ->url(fn (Post $record): ?string => $record->isPublished() ? $record->publicUrl() : null, shouldOpenInNewTab: true)
                        ->placeholder('Belum terbit')->columnSpanFull(),
                ]),
            Section::make('Media sosial')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ImageEntry::make('cover')->label('Gambar')->disk('public')->imageHeight(180)->placeholder('Tidak ada gambar'),
                    TextEntry::make('cover_download')->label('Unduh gambar')
                        ->state(fn (Post $record): ?HtmlString => $record->cover ? new HtmlString(
                            '<a href="'.e(route('panel.posts.image', $record)).'" style="text-decoration: underline; font-weight: 600;">'
                            .($record->cover_original_path ? 'Unduh file asli (resolusi penuh)' : 'Unduh gambar (WebP)').'</a>'
                        ) : null)
                        ->placeholder('-'),
                    TextEntry::make('social_caption')->label('Caption medsos')->copyable()->copyMessage('Caption disalin')
                        ->placeholder('Belum ada caption')->extraAttributes(['style' => 'white-space: pre-line'])->columnSpanFull(),
                    TextEntry::make('social_post_url')->label('Link postingan')->placeholder('Belum diposting')
                        ->url(fn (Post $record): ?string => $record->social_post_url, shouldOpenInNewTab: true),
                    TextEntry::make('social_posted_at')->label('Diposting')->placeholder('-')
                        ->formatStateUsing(fn (Post $record): string => Format::date($record->social_posted_at)),
                ]),
            Section::make('Tugas konten tertaut')
                ->columnSpanFull()
                ->visible(fn (Post $record): bool => $record->event_assignee_id !== null)
                ->schema([
                    TextEntry::make('eventAssignee.event.title')->label('Tugas'),
                    TextEntry::make('eventAssignee.user.name')->label('Penanggung jawab'),
                    TextEntry::make('eventAssignee.result_url')->label('Link hasil')->placeholder('-')
                        ->url(fn (Post $record): ?string => $record->eventAssignee?->result_url, shouldOpenInNewTab: true),
                ]),
            Section::make('Pratinjau isi')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    TextEntry::make('excerpt')->label('Ringkasan')->placeholder('-'),
                    TextEntry::make('body')->label('Isi')->placeholder('-')
                        ->formatStateUsing(fn (Post $record): HtmlString => new HtmlString($record->safeBody())),
                    TextEntry::make('attachment_name')->label('Lampiran')->placeholder('-')
                        ->url(fn (Post $record): ?string => $record->attachmentUrl(), shouldOpenInNewTab: true),
                ]),
            Section::make('Riwayat persetujuan')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('approvals')->hiddenLabel()->columns(4)->placeholder('Belum diajukan')->schema([
                        TextEntry::make('stage')->label('Tahap')->formatStateUsing(fn ($state): string => $state->label()),
                        TextEntry::make('decision')->label('Keputusan')->badge()
                            ->formatStateUsing(fn ($state): string => $state->label())
                            ->color(fn ($state): string => $state->color()),
                        TextEntry::make('user.name')->label('Oleh')->placeholder('-'),
                        TextEntry::make('created_at')->label('Waktu')
                            ->formatStateUsing(fn (PostApproval $record): string => "Putaran {$record->round} · ".Format::date($record->created_at).', '.$record->created_at->format('H.i')),
                        TextEntry::make('note')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'view' => ViewPost::route('/{record}'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }

    private static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
