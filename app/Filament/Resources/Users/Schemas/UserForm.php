<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data pengurus')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Nomor HP')
                            ->tel()
                            ->maxLength(30)
                            ->regex('/^[0-9+\-\s()]{8,30}$/'),
                        Select::make('division_id')
                            ->label('Divisi')
                            ->relationship('division', 'name')
                            ->preload()
                            ->placeholder('Tanpa divisi (mis. Pembina)'),
                        TextInput::make('position')
                            ->label('Jabatan')
                            ->placeholder('mis. Ketua Umum, Koordinator Kajian')
                            ->maxLength(100),
                        Select::make('roles')
                            ->label('Role')
                            ->multiple()
                            ->required()
                            ->options(fn (): array => self::roleOptions())
                            ->visible(fn (?User $record): bool => $record === null
                                ? (bool) Auth::user()?->can('user.manage')
                                : (bool) Auth::user()?->can('manageRoles', $record))
                            ->helperText('Role Super Admin hanya bisa diberikan oleh Super Admin.'),
                    ]),
                Section::make('Foto')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Foto profil')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('users')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->rules(['mimes:jpg,jpeg,png,webp'])
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.($file->guessExtension() ?: 'jpg')
                            )
                            ->helperText('JPG/PNG/WebP, maksimal 2 MB.'),
                    ]),
            ]);
    }

    /** @return array<string, string> */
    private static function roleOptions(): array
    {
        $actor = Auth::user();

        return Role::query()
            ->orderBy('id')
            ->pluck('name')
            ->reject(fn (string $name): bool => $name === RoleName::SuperAdmin->value
                && ! ($actor instanceof User && $actor->isSuperAdmin()))
            ->mapWithKeys(fn (string $name): array => [$name => RoleName::labelFor($name)])
            ->all();
    }
}
