<?php

namespace App\Filament\Resources\Dues\Schemas;

use App\Enums\DueType;
use App\Services\KasService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Nama iuran')
                        ->placeholder('PDH, tabungan jalan-jalan, …')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),
                    TextInput::make('amount')
                        ->label(fn (Get $get): string => $get('type') === DueType::Monthly->value ? 'Nominal per bulan' : 'Nominal')
                        ->prefix('Rp')
                        ->numeric()
                        ->integer()
                        ->minValue(1000)
                        ->maxValue(10_000_000)
                        ->required(),
                    Select::make('type')
                        ->label('Tipe')
                        ->options(DueType::options())
                        ->default(DueType::Once->value)
                        ->required()
                        ->live(),
                    DatePicker::make('due_date')
                        ->label(fn (Get $get): string => $get('type') === DueType::Monthly->value ? 'Tenggat bulan pertama' : 'Tenggat')
                        ->native(false)
                        ->displayFormat('j F Y')
                        ->required()
                        ->helperText(fn (Get $get): string => $get('type') === DueType::Monthly->value
                            ? 'Ditagih setiap bulan pada tanggal yang sama sampai akhir periode aktif.'
                            : 'Iuran sekali bayar ditagih penuh sejak dibuat; boleh dicicil.'),
                    Toggle::make('applies_to_all')
                        ->label('Berlaku untuk semua pengurus wajib kas')
                        ->default(true)
                        ->live()
                        ->columnSpanFull(),
                    Select::make('members')
                        ->label('Peserta')
                        ->relationship(
                            'members',
                            'name',
                            modifyQueryUsing: fn ($query) => $query->whereIn('users.id', app(KasService::class)->payersQuery()->select('users.id')),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => ! $get('applies_to_all'))
                        ->visible(fn (Get $get): bool => ! $get('applies_to_all'))
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
