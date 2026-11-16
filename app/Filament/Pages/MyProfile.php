<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Filament\Support\WebpImageUpload;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

/**
 * Profil Saya: semua pengurus (termasuk pembina) mengubah foto, nomor HP, dan password
 * MILIKNYA SENDIRI. Selalu bekerja pada user yang login — tidak ada ID di URL/form.
 * Nama, email, role, dan divisi hanya ditampilkan (diubah lewat Super Admin).
 *
 * @property-read Schema $profileForm
 * @property-read Schema $passwordForm
 */
class MyProfile extends Page
{
    protected static ?string $slug = 'profil-saya';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $profileData = [];

    /** @var array<string, mixed>|null */
    public ?array $passwordData = [];

    public function mount(): void
    {
        $this->fillProfileForm();
        $this->passwordForm->fill();
    }

    #[Unexposed]
    public function getTitle(): string|Htmlable
    {
        return 'Profil Saya';
    }

    #[Unexposed]
    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('profileData')
            ->components([
                WebpImageUpload::make('photo', 'users', 600)
                    ->label('Foto profil')
                    ->avatar()
                    ->helperText('Foto ini dapat tampil di halaman publik Struktur Kepengurusan. Gunakan foto formal/rapi. '
                        .'JPG/PNG/WebP; foto besar dari HP otomatis diperkecil sebelum diunggah lalu dikompres ke WebP.'),
                TextInput::make('phone')
                    ->label('Nomor HP')
                    ->tel()
                    ->maxLength(30)
                    ->regex('/^[0-9+\-\s()]{8,30}$/'),
            ]);
    }

    #[Unexposed]
    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('passwordData')
            ->components([
                TextInput::make('current_password')
                    ->label('Password saat ini')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword()
                    ->autocomplete('current-password'),
                TextInput::make('password')
                    ->label('Password baru')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->different('current_password')
                    ->helperText('Minimal 10 karakter, berisi huruf, angka, dan simbol. Session di perangkat lain akan diakhiri.')
                    ->autocomplete('new-password'),
                TextInput::make('password_confirmation')
                    ->label('Ulangi password baru')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password')
                    ->autocomplete('new-password'),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $user = $this->user();

        return $schema
            ->components([
                Section::make('Data akun')
                    ->description('Perubahan nama, email, jabatan, divisi, dan role dilakukan oleh Super Admin.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('account_name')->label('Nama')->state($user->name),
                        TextEntry::make('account_email')->label('Email')->state($user->email),
                        TextEntry::make('account_position')->label('Jabatan')->state($user->position)->placeholder('-'),
                        TextEntry::make('account_division')->label('Divisi')->state($user->division?->name)->placeholder('Tanpa divisi'),
                        TextEntry::make('account_roles')
                            ->label('Role')
                            ->state($user->getRoleNames()->all())
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => RoleName::labelFor($state))
                            ->color(fn (string $state): string => RoleName::colorFor($state))
                            ->columnSpanFull(),
                    ]),
                Section::make('Foto & nomor HP')
                    ->schema([
                        Form::make([EmbeddedSchema::make('profileForm')])
                            ->id('profile-form')
                            ->livewireSubmitHandler('saveProfile')
                            ->footer([
                                Actions::make([
                                    Action::make('saveProfile')->label('Simpan profil')->submit('saveProfile'),
                                ])->key('profile-form-actions'),
                                View::make('filament.partials.processing-photo')->viewData(['target' => 'saveProfile']),
                            ]),
                    ]),
                Section::make('Ganti password')
                    ->schema([
                        Form::make([EmbeddedSchema::make('passwordForm')])
                            ->id('password-form')
                            ->livewireSubmitHandler('savePassword')
                            ->footer([
                                Actions::make([
                                    Action::make('savePassword')->label('Ganti password')->submit('savePassword'),
                                ])->key('password-form-actions'),
                            ]),
                    ]),
                $this->twoFactorSection(),
            ]);
    }

    /** 2FA (Fase 10): wajib untuk Ketua/Wakil, Sekretaris, Bendahara; sukarela untuk role lain. */
    private function twoFactorSection(): Section
    {
        $user = $this->user();

        return Section::make('Autentikasi dua langkah (2FA)')
            ->description($user->requiresTwoFactor()
                ? 'Wajib untuk jabatan Anda. Simpan kode pemulihan di tempat aman — dipakai bila HP hilang.'
                : 'Opsional, tetapi sangat disarankan. Saat login, selain password Anda memasukkan kode 6 digit dari aplikasi authenticator.')
            ->schema(collect(Filament::getMultiFactorAuthenticationProviders())
                ->map(fn (MultiFactorAuthenticationProvider $provider) => Group::make($provider->getManagementSchemaComponents())
                    ->statePath($provider->getId()))
                ->values()
                ->all());
    }

    #[Exposed]
    public function saveProfile(): void
    {
        $data = $this->profileForm->getState();

        // Whitelist ketat di service: hanya photo & phone milik user yang login.
        app(UserAccountService::class)->updateOwnProfile($this->user(), [
            'photo' => $data['photo'] ?? null,
            'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
        ]);

        $this->fillProfileForm();

        Notification::make()->title('Profil disimpan')->success()->send();
    }

    #[Exposed]
    public function savePassword(): void
    {
        $data = $this->passwordForm->getState();

        app(UserAccountService::class)->changeOwnPassword($this->user(), $data['password'], session()->getId());

        $this->passwordForm->fill();

        Notification::make()
            ->title('Password berhasil diganti')
            ->body('Session di perangkat lain telah diakhiri.')
            ->success()
            ->send();
    }

    protected function fillProfileForm(): void
    {
        $user = $this->user()->fresh();

        $this->profileForm->fill([
            'photo' => $user->photo,
            'phone' => $user->phone,
        ]);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
