<?php

namespace App\Filament\Resources\ApplicantAccounts;

use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ApplicantAccounts\Pages\CreateApplicantAccount;
use App\Filament\Resources\ApplicantAccounts\Pages\EditApplicantAccount;
use App\Filament\Resources\ApplicantAccounts\Pages\ListApplicantAccounts;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Organization;
use App\Models\User;
use App\Services\ApplicantAccountService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Akun pengaju kegiatan (keputusan Fase 7). Hanya menampilkan & mengelola akun yang role-nya MURNI
 * pengaju_kegiatan — akun pengurus BEM tidak pernah muncul/ tersentuh di sini (404 walau ID ditebak).
 * Semua perubahan lewat ApplicantAccountService. Dikecualikan dari permission resource Shield.
 */
class ApplicantAccountResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 31;

    protected static ?string $modelLabel = 'akun pengaju';

    protected static ?string $pluralModelLabel = 'akun pengaju';

    protected static ?string $navigationLabel = 'Akun Pengaju Kegiatan';

    protected static ?string $slug = 'akun-pengaju';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Satu titik otorisasi untuk semua halaman & aksi resource ini (bukan UserPolicy):
     * applicant.manage + target harus akun murni pengaju; hapus tidak pernah diizinkan.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;
        $allowed = (bool) self::actor()?->can('applicant.manage')
            && ! in_array($action, ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny'], true)
            && ($record === null || ($record instanceof User && ApplicantAccountService::isPureApplicant($record)));

        return $allowed ? Response::allow() : Response::deny();
    }

    /** Hanya akun yang role-nya tepat satu: pengaju_kegiatan. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['organization', 'roles'])
            ->role(RoleName::PengajuKegiatan->value)
            ->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', '!=', RoleName::PengajuKegiatan->value));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(100),
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(150),
                    TextInput::make('phone')->label('Nomor HP')->tel()->maxLength(30),
                    Select::make('organization_id')->label('Organisasi')->required()->searchable()
                        ->options(fn () => Organization::query()->active()->where('type', '!=', OrganizationType::Bem->value)->orderBy('name')->pluck('name', 'id')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->weight('semibold')
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('organization.name')->label('Organisasi')->placeholder('-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')->label('Login terakhir')->since()->placeholder('Belum pernah'),
            ])
            ->filters([
                SelectFilter::make('organization_id')->label('Organisasi')->relationship('organization', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('resetPassword')->label('Reset password')->icon(Heroicon::OutlinedKey)->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (User $record) => self::run(function () use ($record): void {
                            $password = app(ApplicantAccountService::class)->resetPassword(self::actor(), $record);
                            UsersTable::notifyTemporaryPassword($record, $password);
                        })),
                    Action::make('deactivate')->label('Nonaktifkan')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->requiresConfirmation()->visible(fn (User $record): bool => $record->is_active)
                        ->action(fn (User $record) => self::run(fn () => app(ApplicantAccountService::class)->deactivate(self::actor(), $record), 'Akun dinonaktifkan')),
                    Action::make('activate')->label('Aktifkan')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                        ->requiresConfirmation()->visible(fn (User $record): bool => ! $record->is_active)
                        ->action(fn (User $record) => self::run(fn () => app(ApplicantAccountService::class)->activate(self::actor(), $record), 'Akun diaktifkan')),
                ]),
            ])
            ->emptyStateHeading('Belum ada akun pengaju')
            ->emptyStateDescription('Buat akun per orang untuk pengurus UKM/HMJ/panitia yang akan mengajukan kegiatan.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplicantAccounts::route('/'),
            'create' => CreateApplicantAccount::route('/create'),
            'edit' => EditApplicantAccount::route('/{record}/edit'),
        ];
    }

    private static function run(callable $callback, ?string $success = null): void
    {
        try {
            $callback();
        } catch (AuthorizationException|BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        if ($success) {
            Notification::make()->title($success)->success()->send();
        }
    }

    private static function actor(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
