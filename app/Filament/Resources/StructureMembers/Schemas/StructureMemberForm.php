<?php

namespace App\Filament\Resources\StructureMembers\Schemas;

use App\Filament\Support\WebpImageUpload;
use App\Models\Period;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StructureMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->description('Data ini tampil di halaman publik Struktur. Email dan nomor HP akun tidak ditampilkan.')
                    ->schema([
                        Select::make('period_id')
                            ->label('Periode')
                            ->relationship('period', 'name')
                            ->default(fn (): ?int => Period::current()?->id)
                            ->required()
                            ->preload(),
                        Select::make('division_id')
                            ->label('Divisi')
                            ->relationship('division', 'name')
                            ->preload()
                            ->placeholder('Tanpa divisi (tampil di bagian Pimpinan)'),
                        Select::make('user_id')
                            ->label('Tautkan ke akun pengurus (opsional)')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                $user = $state ? User::query()->find($state) : null;

                                if (! $user) {
                                    return;
                                }

                                if (blank($get('name'))) {
                                    $set('name', $user->name);
                                }
                                if (blank($get('position')) && $user->position) {
                                    $set('position', $user->position);
                                }
                                if (blank($get('division_id')) && $user->division_id) {
                                    $set('division_id', $user->division_id);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('name')->label('Nama')->required()->maxLength(150),
                        TextInput::make('position')->label('Jabatan')->required()->maxLength(100)
                            ->placeholder('mis. Ketua Umum, Koordinator Kajian'),
                        TextInput::make('prodi')->label('Program studi')->maxLength(100),
                        TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->default(0)
                            ->helperText('Angka kecil tampil lebih dulu.'),
                        WebpImageUpload::make('photo', 'structure', 600)
                            ->label('Foto')
                            ->avatar()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
