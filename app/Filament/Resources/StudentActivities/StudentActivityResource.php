<?php

namespace App\Filament\Resources\StudentActivities;

use App\Enums\ActivityStatus;
use App\Filament\Resources\StudentActivities\Pages\CreateStudentActivity;
use App\Filament\Resources\StudentActivities\Pages\EditStudentActivity;
use App\Filament\Resources\StudentActivities\Pages\ListStudentActivities;
use App\Filament\Resources\StudentActivities\Pages\ViewStudentActivity;
use App\Models\Division;
use App\Models\StudentActivity;
use App\Models\StudentActivityLog;
use App\Models\User;
use App\Support\Format;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Kegiatan (proposal & LPJ). Pengurus BEM melihatnya sebagai "Kegiatan"; pengaju sebagai "Kegiatan Saya".
 * Daftar dibatasi StudentActivity::visibleTo; semua aksi lewat ActivityWorkflowService.
 * Dijaga StudentActivityPolicy; dikecualikan dari permission resource Shield.
 */
class StudentActivityResource extends Resource
{
    protected static ?string $model = StudentActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'kegiatan';

    protected static ?string $pluralModelLabel = 'kegiatan';

    protected static ?string $slug = 'kegiatan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::user()?->isApplicant() ? null : 'BPH';
    }

    public static function getNavigationLabel(): string
    {
        return self::user()?->isApplicant() ? 'Kegiatan Saya' : 'Kegiatan (Proposal & LPJ)';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = self::user();

        if (! $user || ! StudentActivityPolicyHelper::reviewsSomething($user)) {
            return null;
        }

        $count = StudentActivity::query()->whereIn('status', StudentActivityPolicyHelper::statusesFor($user))
            ->where('pic_user_id', '!=', $user->getKey())->count();

        return $count > 0 ? (string) $count : null;
    }

    /** @return Builder<StudentActivity> */
    public static function getEloquentQuery(): Builder
    {
        $user = self::user();

        return parent::getEloquentQuery()->with(['organization', 'pic'])
            ->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function form(Schema $schema): Schema
    {
        $isStaff = ! self::user()?->isApplicant();

        return $schema->components([
            Section::make('Data kegiatan')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Nama kegiatan')->required()->maxLength(200)->columnSpanFull(),
                    DatePicker::make('starts_on')->label('Tanggal mulai')->required()->native(false)->displayFormat('j F Y'),
                    DatePicker::make('ends_on')->label('Tanggal selesai')->required()->native(false)->displayFormat('j F Y')->afterOrEqual('starts_on'),
                    TextInput::make('location')->label('Lokasi')->maxLength(200),
                    TextInput::make('budget_estimate')->label('Perkiraan anggaran')->prefix('Rp')->numeric()->integer()->minValue(0)->required(),
                    Select::make('division_id')->label('Divisi pelaksana (opsional)')
                        ->options(fn () => Division::query()->orderBy('name')->pluck('name', 'id'))
                        ->visible($isStaff)
                        ->helperText('Kegiatan internal BEM. Koordinator divisi ini dapat melihatnya.'),
                    Textarea::make('description')->label('Deskripsi singkat')->rows(3)->maxLength(5000)->columnSpanFull(),
                    FileUpload::make('proposal_path')->label('File proposal (PDF)')
                        ->disk(StudentActivity::DISK)
                        ->directory(StudentActivity::DIRECTORY)
                        ->visibility('private')
                        ->maxSize(10240)
                        ->acceptedFileTypes(['application/pdf'])
                        ->rules(['mimes:pdf'])
                        ->storeFileNamesIn('proposal_name')
                        ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.pdf')
                        ->helperText('PDF maksimal 10 MB. Disimpan privat; wajib ada sebelum diajukan.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isApplicant = (bool) self::user()?->isApplicant();

        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('name')->label('Kegiatan')->searchable()->wrap()->weight('semibold')
                    ->description(fn (StudentActivity $r): ?string => $r->organization?->name),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (ActivityStatus $state): string => $state->label())
                    ->color(fn (ActivityStatus $state): string => $state->color()),
                TextColumn::make('lpj_due')->label('Tenggat LPJ')
                    ->state(fn (StudentActivity $r): ?string => $r->status->awaitsLpj() ? Format::date($r->lpjDueOn()) : null)
                    ->badge(fn (StudentActivity $r): bool => $r->isLpjOverdue())
                    ->color(fn (StudentActivity $r): ?string => $r->isLpjOverdue() ? 'danger' : null)
                    ->description(fn (StudentActivity $r): ?string => $r->isLpjOverdue() ? 'Terlambat' : null)
                    ->placeholder('-'),
                TextColumn::make('starts_on')->label('Tanggal')
                    ->formatStateUsing(fn (StudentActivity $r): string => Format::date($r->starts_on).($r->ends_on->isSameDay($r->starts_on) ? '' : ' – '.Format::date($r->ends_on)))
                    ->sortable(),
                TextColumn::make('pic.name')->label('Penanggung jawab'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ActivityStatus::options()),
                SelectFilter::make('organization_id')->label('Organisasi')->relationship('organization', 'name')->visible(! $isApplicant),
            ])
            ->recordUrl(fn (StudentActivity $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada kegiatan')
            ->emptyStateDescription($isApplicant ? 'Buat kegiatan baru lalu ajukan proposalnya.' : null)
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('name')->label('Kegiatan')->weight('bold')->columnSpanFull(),
                    TextEntry::make('status')->label('Status')->badge()
                        ->formatStateUsing(fn (ActivityStatus $state): string => $state->label())
                        ->color(fn (ActivityStatus $state): string => $state->color()),
                    TextEntry::make('organization.name')->label('Organisasi'),
                    TextEntry::make('division.name')->label('Divisi pelaksana')->placeholder('-'),
                    TextEntry::make('pic.name')->label('Penanggung jawab'),
                    TextEntry::make('dates')->label('Tanggal')
                        ->state(fn (StudentActivity $r): string => Format::date($r->starts_on).' – '.Format::date($r->ends_on)),
                    TextEntry::make('location')->label('Lokasi')->placeholder('-'),
                    TextEntry::make('budget_estimate')->label('Perkiraan anggaran')->formatStateUsing(fn (int $state): string => Format::rupiah($state)),
                    TextEntry::make('budget_realization')->label('Realisasi anggaran')->placeholder('-')
                        ->formatStateUsing(fn (?int $state): string => Format::rupiah($state)),
                    TextEntry::make('lpj_due')->label('Tenggat LPJ')
                        ->state(fn (StudentActivity $r): ?string => in_array($r->status, [ActivityStatus::Approved, ActivityStatus::LpjSubmitted, ActivityStatus::LpjVerified, ActivityStatus::LpjBudgetReviewed, ActivityStatus::LpjRevision, ActivityStatus::Completed], true)
                            ? Format::date($r->lpjDueOn()).($r->isLpjOverdue() ? ' · TERLAMBAT' : '') : null)
                        ->color(fn (StudentActivity $r): ?string => $r->isLpjOverdue() ? 'danger' : null)
                        ->weight(fn (StudentActivity $r): ?string => $r->isLpjOverdue() ? 'bold' : null)
                        ->placeholder('-'),
                    TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
                    TextEntry::make('files')->label('Berkas')->columnSpanFull()
                        ->state(fn (StudentActivity $r): HtmlString => new HtmlString(collect([
                            $r->proposal_path ? '<a style="text-decoration: underline; font-weight: 600;" href="'.e(route('panel.activities.file', [$r, 'proposal'])).'">Unduh proposal: '.e((string) $r->proposal_name).'</a>' : null,
                            $r->lpj_path ? '<a style="text-decoration: underline; font-weight: 600;" href="'.e(route('panel.activities.file', [$r, 'lpj'])).'">Unduh LPJ: '.e((string) $r->lpj_name).'</a>' : null,
                        ])->filter()->implode('<br>') ?: '-')),
                    TextEntry::make('event_id')->label('Kalender')->placeholder('Belum masuk kalender')
                        ->formatStateUsing(fn (): string => 'Tampil di Kalender Kegiatan publik')
                        ->url(fn (): string => route('public.calendar'), shouldOpenInNewTab: true),
                ]),
            Section::make('Riwayat')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('logs')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('stage')->label('Tahap')->formatStateUsing(fn ($state, StudentActivityLog $record): string => $record->stageLabel()),
                        TextEntry::make('to_status')->label('Status')->placeholder('-')->badge()
                            ->formatStateUsing(fn (?ActivityStatus $state): string => $state?->label() ?? '-')
                            ->color(fn (?ActivityStatus $state): string => $state?->color() ?? 'gray'),
                        TextEntry::make('user.name')->label('Oleh')->placeholder('-'),
                        TextEntry::make('created_at')->label('Waktu')
                            ->formatStateUsing(fn (StudentActivityLog $record): string => Format::date($record->created_at).', '.$record->created_at->format('H.i')),
                        TextEntry::make('note')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentActivities::route('/'),
            'create' => CreateStudentActivity::route('/create'),
            'view' => ViewStudentActivity::route('/{record}'),
            'edit' => EditStudentActivity::route('/{record}/edit'),
        ];
    }

    private static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
