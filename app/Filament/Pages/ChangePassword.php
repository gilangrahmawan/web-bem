<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

/**
 * Ganti password. Wajib dibuka saat must_change_password=true (login pertama / setelah reset),
 * dan bisa dibuka kapan saja dari menu user. Session lain diakhiri setelah berhasil.
 *
 * @property-read Schema $form
 */
class ChangePassword extends Page
{
    protected static ?string $slug = 'ganti-password';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    #[Unexposed]
    public function getTitle(): string|Htmlable
    {
        return 'Ganti Password';
    }

    #[Unexposed]
    public function getSubheading(): string|Htmlable|null
    {
        return $this->mustChange()
            ? 'Demi keamanan, Anda wajib mengganti password sebelum memakai dashboard.'
            : null;
    }

    #[Unexposed]
    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->maxWidth('xl')
                    ->schema([
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
                            ->helperText('Minimal 10 karakter, berisi huruf, angka, dan simbol.')
                            ->autocomplete('new-password'),
                        TextInput::make('password_confirmation')
                            ->label('Ulangi password baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('password')
                            ->autocomplete('new-password'),
                    ]),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Simpan password baru')->submit('save'),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    #[Exposed]
    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Filament::auth()->user();

        app(UserAccountService::class)->changeOwnPassword($user, $data['password'], session()->getId());

        $this->form->fill();

        Notification::make()
            ->title('Password berhasil diganti')
            ->body('Session di perangkat lain telah diakhiri.')
            ->success()
            ->send();

        $this->redirect(Filament::getUrl());
    }

    protected function mustChange(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->must_change_password;
    }
}
