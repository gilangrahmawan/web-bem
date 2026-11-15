<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\Division;
use App\Models\Period;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        $restricted = ProgramResource::restrictedDivisionId();

        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('division_id')
                            ->label('Divisi')
                            ->options(fn (): array => Division::query()
                                ->when($restricted !== null, fn ($q) => $q->whereKey($restricted))
                                ->orderBy('id')->pluck('name', 'id')->all())
                            ->default($restricted)
                            ->disabled($restricted !== null)
                            ->dehydrated($restricted === null)
                            ->required($restricted === null)
                            ->helperText($restricted !== null ? 'Koordinator hanya bisa mengelola program divisinya sendiri.' : null),
                        Select::make('period_id')
                            ->label('Periode')
                            ->options(fn (): array => Period::query()->orderByDesc('starts_on')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => Period::current()?->id)
                            ->required(),
                        TextInput::make('title')
                            ->label('Nama program')
                            ->required()
                            ->maxLength(200)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(4)
                            ->maxLength(3000)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(ProgramStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                            ->default(ProgramStatus::Planned->value)
                            ->required()
                            ->native(false),
                        DatePicker::make('starts_on')->label('Mulai')->native(false)->displayFormat('d F Y'),
                        DatePicker::make('ends_on')->label('Selesai')->native(false)->displayFormat('d F Y')
                            ->afterOrEqual('starts_on'),
                    ]),
            ]);
    }
}
