<?php

namespace App\Filament\Resources\Organizations;

use App\Enums\OrganizationType;
use App\Filament\Resources\Organizations\Pages\ManageOrganizations;
use App\Models\Organization;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Daftar organisasi pengaju kegiatan (Super Admin & Sekretaris). */
class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'organisasi';

    protected static ?string $pluralModelLabel = 'organisasi';

    protected static ?string $navigationLabel = 'Organisasi';

    protected static ?string $slug = 'organisasi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama organisasi')->required()->maxLength(150)->unique(ignoreRecord: true),
            Select::make('type')->label('Jenis')->options(OrganizationType::options())->required(),
            Toggle::make('is_active')->label('Aktif')->default(true)
                ->helperText('Akun pengaju dari organisasi nonaktif tidak bisa login.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['activities', 'applicants']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Organisasi')->searchable()->weight('semibold'),
                TextColumn::make('type')->label('Jenis')->badge()
                    ->formatStateUsing(fn (OrganizationType $state): string => $state->label())
                    ->color(fn (OrganizationType $state): string => $state->color()),
                TextColumn::make('applicants_count')->label('Akun aktif'),
                TextColumn::make('activities_count')->label('Kegiatan'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([SelectFilter::make('type')->label('Jenis')->options(OrganizationType::options())])
            ->recordActions([
                EditAction::make()->after(fn (Organization $record) => app(ActivityLogger::class)->log('organization.updated', $record, [
                    'changed' => array_keys($record->getChanges()),
                ])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrganizations::route('/')];
    }
}
