<?php

namespace App\Filament\Resources\Periods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->description('Gunakan tombol "Jadikan aktif" di daftar periode untuk menetapkan periode berjalan.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama kabinet')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        DatePicker::make('starts_on')
                            ->label('Mulai')
                            ->native(false)
                            ->displayFormat('d F Y')
                            ->required(),
                        DatePicker::make('ends_on')
                            ->label('Selesai')
                            ->native(false)
                            ->displayFormat('d F Y')
                            ->required()
                            ->after('starts_on'),
                    ]),
            ]);
    }
}
