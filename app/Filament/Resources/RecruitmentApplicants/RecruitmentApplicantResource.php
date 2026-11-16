<?php

namespace App\Filament\Resources\RecruitmentApplicants;

use App\Enums\ApplicantStatus;
use App\Filament\Resources\RecruitmentApplicants\Pages\ListRecruitmentApplicants;
use App\Filament\Resources\RecruitmentApplicants\Pages\ViewRecruitmentApplicant;
use App\Filament\Resources\Recruitments\RecruitmentResource;
use App\Models\Division;
use App\Models\RecruitmentApplicant;
use App\Models\RecruitmentApplicantLog;
use App\Models\RecruitmentInterview;
use App\Models\User;
use App\Support\Format;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\ImageEntry;
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
use UnitEnum;

/**
 * Pendaftar oprec. Daftar dibatasi RecruitmentApplicant::visibleTo (koordinator hanya pendaftar yang
 * memilih divisinya). Nomor WA & email hanya untuk pengelola oprec. Nilai & catatan interview internal.
 * Dijaga RecruitmentApplicantPolicy; dikecualikan dari permission resource Shield.
 */
class RecruitmentApplicantResource extends Resource
{
    protected static ?string $model = RecruitmentApplicant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'BPH';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'pendaftar oprec';

    protected static ?string $pluralModelLabel = 'pendaftar oprec';

    protected static ?string $navigationLabel = 'Oprec: Pendaftar';

    protected static ?string $slug = 'oprec-pendaftar';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return Builder<RecruitmentApplicant> */
    public static function getEloquentQuery(): Builder
    {
        $user = self::user();

        return parent::getEloquentQuery()
            ->with(['recruitment', 'firstChoice', 'secondChoice', 'acceptedDivision'])
            ->withAvg('interviews', 'score')
            ->when($user, fn (Builder $query) => $query->visibleTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function table(Table $table): Table
    {
        $divisions = fn () => Division::query()->whereIn('slug', RecruitmentResource::OPENABLE_DIVISIONS)->orderBy('name')->pluck('name', 'id');

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('registration_code')->label('Kode')->fontFamily('mono')->searchable()->toggleable(),
                TextColumn::make('name')->label('Nama')->searchable()->weight('semibold')
                    ->description(fn (RecruitmentApplicant $r): string => $r->nim.' · '.$r->prodi.' '.$r->batch_year),
                TextColumn::make('nim')->label('NIM')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('firstChoice.name')->label('Pilihan 1'),
                TextColumn::make('secondChoice.name')->label('Pilihan 2')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (ApplicantStatus $state): string => $state->label())
                    ->color(fn (ApplicantStatus $state): string => $state->color()),
                TextColumn::make('interviews_avg_score')->label('Nilai rata-rata')->placeholder('-')->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.')),
                TextColumn::make('interview_at')->label('Interview')->placeholder('-')->sortable()
                    ->formatStateUsing(fn (RecruitmentApplicant $r): string => Format::date($r->interview_at).', '.$r->interview_at->format('H.i')),
                TextColumn::make('recruitment.name')->label('Gelombang')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Mendaftar')->sortable()->toggleable()
                    ->formatStateUsing(fn (RecruitmentApplicant $r): string => Format::date($r->created_at)),
            ])
            ->filters([
                SelectFilter::make('recruitment_id')->label('Gelombang')->relationship('recruitment', 'name'),
                SelectFilter::make('division')->label('Divisi (pilihan 1/2)')->options($divisions)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query
                        ->where(fn (Builder $query) => $query->where('first_choice_division_id', $id)->orWhere('second_choice_division_id', $id)))),
                SelectFilter::make('status')->label('Status')->options(ApplicantStatus::options()),
            ])
            ->recordUrl(fn (RecruitmentApplicant $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada pendaftar')
            ->emptyStateDescription('Pendaftar masuk lewat form publik /oprec saat gelombang dibuka.')
            ->emptyStateIcon(Heroicon::OutlinedUserPlus);
    }

    public static function infolist(Schema $schema): Schema
    {
        $canManage = fn (RecruitmentApplicant $r): bool => self::user()?->can('update', $r) ?? false;

        return $schema->components([
            Section::make()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    ImageEntry::make('photo')->hiddenLabel()->imageHeight(140)->columnSpan(1)
                        ->state(fn (RecruitmentApplicant $r): ?string => $r->photo_path ? route('panel.recruitment.file', [$r, 'photo']) : null)
                        ->placeholder('Foto tidak tersedia'),
                    Section::make()->columnSpan(2)->columns(2)->contained(false)->schema([
                        TextEntry::make('name')->label('Nama')->weight('bold'),
                        TextEntry::make('status')->label('Status')->badge()
                            ->formatStateUsing(fn (ApplicantStatus $state): string => $state->label())
                            ->color(fn (ApplicantStatus $state): string => $state->color()),
                        TextEntry::make('registration_code')->label('Kode pendaftaran')->fontFamily('mono'),
                        TextEntry::make('nim')->label('NIM'),
                        TextEntry::make('prodi')->label('Prodi / angkatan')
                            ->formatStateUsing(fn (RecruitmentApplicant $r): string => $r->prodi.' / '.$r->batch_year),
                        TextEntry::make('recruitment.name')->label('Gelombang'),
                    ]),
                    TextEntry::make('firstChoice.name')->label('Pilihan 1'),
                    TextEntry::make('secondChoice.name')->label('Pilihan 2')->placeholder('-'),
                    TextEntry::make('acceptedDivision.name')->label('Diterima di divisi')->placeholder('-'),
                    TextEntry::make('phone')->label('WhatsApp')->visible($canManage)
                        ->placeholder(fn (RecruitmentApplicant $r): string => $r->data_purged_at ? 'Dihapus (masa simpan habis)' : '-'),
                    TextEntry::make('email')->label('Email')->visible($canManage)
                        ->placeholder(fn (RecruitmentApplicant $r): string => $r->data_purged_at ? 'Dihapus (masa simpan habis)' : '-'),
                    TextEntry::make('cv')->label('CV')
                        ->state(fn (RecruitmentApplicant $r): HtmlString|string => $r->cv_path
                            ? new HtmlString('<a style="text-decoration: underline; font-weight: 600;" href="'.e(route('panel.recruitment.file', [$r, 'cv'])).'">Unduh CV: '.e((string) $r->cv_name).'</a>')
                            : ($r->data_purged_at ? 'Dihapus (masa simpan habis)' : '-')),
                    TextEntry::make('interview_at')->label('Jadwal interview')->placeholder('Belum dijadwalkan')
                        ->formatStateUsing(fn (RecruitmentApplicant $r): string => Format::date($r->interview_at).', '.$r->interview_at->format('H.i').' WIB'),
                    TextEntry::make('interview_location')->label('Tempat / link interview')->placeholder('-')->columnSpan(2),
                    TextEntry::make('account.name')->label('Akun pengurus')->placeholder('Belum dibuat')
                        ->formatStateUsing(fn (RecruitmentApplicant $r): string => $r->account->name.($r->account->is_active ? ' (aktif)' : ' (nonaktif — menunggu Super Admin)')),
                    TextEntry::make('motivation')->label('Motivasi')->columnSpanFull(),
                    TextEntry::make('experience')->label('Pengalaman organisasi')->placeholder('-')->columnSpanFull(),
                ]),
            Section::make('Penilaian interview (internal)')
                ->description('Tidak pernah ditampilkan ke pendaftar.')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('average')->label('Nilai rata-rata')->placeholder('Belum ada nilai')
                        ->state(fn (RecruitmentApplicant $r): ?string => ($avg = $r->averageScore()) === null ? null : number_format($avg, 2, ',', '.').' / 5'),
                    RepeatableEntry::make('interviews')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('interviewer.name')->label('Pewawancara'),
                        TextEntry::make('score')->label('Nilai')->formatStateUsing(fn (int $state): string => $state.' / 5'),
                        TextEntry::make('updated_at')->label('Waktu')
                            ->formatStateUsing(fn (RecruitmentInterview $record): string => Format::date($record->updated_at).', '.$record->updated_at->format('H.i')),
                        TextEntry::make('note')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ])->placeholder('Belum ada penilaian.'),
                ]),
            Section::make('Riwayat')
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    RepeatableEntry::make('logs')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('to_status')->label('Status')->badge()
                            ->formatStateUsing(fn (ApplicantStatus $state): string => $state->label())
                            ->color(fn (ApplicantStatus $state): string => $state->color()),
                        TextEntry::make('user.name')->label('Oleh')->placeholder('Pendaftar / sistem'),
                        TextEntry::make('created_at')->label('Waktu')
                            ->formatStateUsing(fn (RecruitmentApplicantLog $record): string => Format::date($record->created_at).', '.$record->created_at->format('H.i')),
                        TextEntry::make('note')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecruitmentApplicants::route('/'),
            'view' => ViewRecruitmentApplicant::route('/{record}'),
        ];
    }

    public static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
