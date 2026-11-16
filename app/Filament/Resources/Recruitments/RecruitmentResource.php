<?php

namespace App\Filament\Resources\Recruitments;

use App\Filament\Resources\RecruitmentApplicants\RecruitmentApplicantResource;
use App\Filament\Resources\Recruitments\Pages\ManageRecruitments;
use App\Models\Division;
use App\Models\Recruitment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\RecruitmentService;
use App\Support\Format;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Gelombang oprec (recruitment.manage: Sekretaris & Super Admin; Pembina lihat).
 * Hanya boleh ada satu gelombang aktif pada rentang waktu yang sama.
 * Dijaga RecruitmentPolicy; dikecualikan dari permission resource Shield.
 */
class RecruitmentResource extends Resource
{
    /** Divisi yang bisa dibuka untuk oprec (BPH dipilih langsung oleh Ketua). */
    public const OPENABLE_DIVISIONS = ['kajian', 'medpub', 'humas'];

    protected static ?string $model = Recruitment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'BPH';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'gelombang oprec';

    protected static ?string $pluralModelLabel = 'gelombang oprec';

    protected static ?string $navigationLabel = 'Oprec: Gelombang';

    protected static ?string $slug = 'oprec';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama gelombang')->required()->maxLength(150)
                ->placeholder('Oprec Pengurus BEM 2027 — Gelombang 1')->columnSpanFull(),
            Textarea::make('description')->label('Keterangan (tampil di form publik)')->rows(3)->maxLength(3000)->columnSpanFull(),
            DateTimePicker::make('opens_at')->label('Dibuka')->required()->seconds(false)->native(false),
            DateTimePicker::make('closes_at')->label('Ditutup')->required()->seconds(false)->native(false)
                ->after('opens_at')
                ->rule(fn (Get $get, ?Recruitment $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    if (! $get('is_active') || blank($get('opens_at')) || blank($value)) {
                        return;
                    }

                    $overlap = Recruitment::query()->where('is_active', true)
                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->where('opens_at', '<=', Carbon::parse($value))
                        ->where('closes_at', '>=', Carbon::parse($get('opens_at')))
                        ->first();

                    if ($overlap) {
                        $fail("Jadwal beririsan dengan gelombang aktif \"{$overlap->name}\". Hanya satu gelombang yang boleh dibuka dalam satu waktu.");
                    }
                }),
            CheckboxList::make('open_divisions')->label('Divisi yang dibuka')->required()->columns(3)->columnSpanFull()
                ->options(fn () => Division::query()->whereIn('slug', self::OPENABLE_DIVISIONS)->orderBy('name')->pluck('name', 'id'))
                ->afterStateHydrated(fn (CheckboxList $component, $state) => $component->state(array_map('strval', (array) $state)))
                ->dehydrateStateUsing(fn ($state): array => array_values(array_map('intval', (array) $state))),
            Toggle::make('is_active')->label('Aktif')->default(true)
                ->helperText('Gelombang nonaktif tidak tampil di form publik walau sedang dalam jadwal.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('applicants'))
            ->defaultSort('opens_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Gelombang')->searchable()->weight('semibold')->wrap(),
                TextColumn::make('schedule')->label('Jadwal')
                    ->state(fn (Recruitment $r): string => Format::date($r->opens_at).' – '.Format::date($r->closes_at)),
                TextColumn::make('divisions')->label('Divisi')->badge()
                    ->state(fn (Recruitment $r): array => $r->openDivisions()->pluck('name')->all()),
                TextColumn::make('applicants_count')->label('Pendaftar')
                    ->url(fn (Recruitment $r): string => RecruitmentApplicantResource::getUrl('index', ['filters' => ['recruitment_id' => ['value' => $r->getKey()]]])),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Recruitment $r): string => match (true) {
                        ! $r->is_active => 'Nonaktif',
                        $r->isOpen() => 'Dibuka',
                        now()->lt($r->opens_at) => 'Belum dibuka',
                        default => 'Ditutup',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Dibuka' => 'success',
                        'Belum dibuka' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('results_published_at')->label('Hasil')->placeholder('Belum diumumkan')
                    ->formatStateUsing(fn (Recruitment $r): string => 'Diumumkan '.Format::date($r->results_published_at)),
            ])
            ->recordActions([
                Action::make('publishResults')
                    ->label('Umumkan hasil')->icon(Heroicon::OutlinedMegaphone)->color('success')
                    ->visible(fn (Recruitment $r): bool => self::user()?->can('publishResults', $r) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Umumkan hasil seleksi?')
                    ->modalDescription('Status diterima/ditolak semua pendaftar gelombang ini akan terlihat di halaman cek hasil. Tindakan ini tidak bisa dibatalkan.')
                    ->modalSubmitActionLabel('Umumkan')
                    ->action(function (Recruitment $record): void {
                        $pending = $record->applicants()->whereNotIn('status', ['accepted', 'rejected'])->count();
                        app(RecruitmentService::class)->publishResults(self::user(), $record);
                        Notification::make()->title('Hasil diumumkan')
                            ->body($pending > 0 ? "{$pending} pendaftar belum diputuskan dan masih tampil sebagai sedang diproses." : null)
                            ->success()->send();
                    }),
                EditAction::make()->after(fn (Recruitment $record) => app(ActivityLogger::class)->log('recruitment.updated', $record, [
                    'changed' => array_keys($record->getChanges()),
                ])),
                DeleteAction::make()
                    ->after(fn (Recruitment $record) => app(ActivityLogger::class)->log('recruitment.deleted', null, ['name' => $record->name])),
            ])
            ->emptyStateHeading('Belum ada gelombang oprec')
            ->emptyStateIcon(Heroicon::OutlinedMegaphone);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRecruitments::route('/')];
    }

    private static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
