<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular()
                    ->defaultImageUrl(fn (User $record): string => InitialsAvatarProvider::forName($record->name)),
                TextColumn::make('name')->label('Nama')->searchable()->sortable()
                    ->description(fn (User $record): ?string => $record->position),
                TextColumn::make('email')->label('Email')->searchable()->copyable()->toggleable(),
                TextColumn::make('phone')->label('HP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('division.name')->label('Divisi')->badge()->color('gray')->placeholder('-'),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleName::labelFor($state))
                    ->color(fn (string $state): string => RoleName::colorFor($state)),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')->label('Login terakhir')->since()->placeholder('Belum pernah')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('division_id')->label('Divisi')->relationship('division', 'name'),
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(fn (): array => Role::query()->orderBy('id')->pluck('name')
                        ->mapWithKeys(fn (string $name): array => [$name => RoleName::labelFor($name)])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->role($data['value'])
                        : $query),
                TernaryFilter::make('is_active')->label('Status')->trueLabel('Aktif')->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    self::resetPasswordAction(),
                    self::deactivateAction(),
                    self::activateAction(),
                ]),
            ]);
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Password lama tidak berlaku lagi dan semua session user ini diakhiri. Password sementara akan ditampilkan satu kali.')
            ->visible(fn (User $record): bool => Gate::allows('resetPassword', $record))
            ->action(function (User $record): void {
                Gate::authorize('resetPassword', $record);
                $password = app(UserAccountService::class)->resetPassword(Auth::user(), $record);

                self::notifyTemporaryPassword($record, $password);
            });
    }

    private static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label('Nonaktifkan')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('User tidak bisa login lagi dan langsung dikeluarkan dari semua perangkat. Data tidak dihapus.')
            ->visible(fn (User $record): bool => Gate::allows('deactivate', $record))
            ->action(function (User $record): void {
                Gate::authorize('deactivate', $record);

                try {
                    app(UserAccountService::class)->deactivate(Auth::user(), $record);
                } catch (BusinessRuleException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('User dinonaktifkan')->success()->send();
            });
    }

    private static function activateAction(): Action
    {
        return Action::make('activate')
            ->label('Aktifkan')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => Gate::allows('activate', $record))
            ->action(function (User $record): void {
                Gate::authorize('activate', $record);

                try {
                    app(UserAccountService::class)->activate(Auth::user(), $record);
                } catch (BusinessRuleException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('User diaktifkan')->success()->send();
            });
    }

    public static function notifyTemporaryPassword(User $user, string $password): void
    {
        Notification::make()
            ->title('Password sementara untuk '.$user->name)
            ->body("Password: {$password} — catat dan berikan langsung ke yang bersangkutan. Password ini hanya ditampilkan sekali dan wajib diganti saat login.")
            ->success()
            ->persistent()
            ->send();
    }
}
