<?php

namespace App\Filament\Resources\Dues;

use App\Filament\Resources\Dues\Pages\CreateDue;
use App\Filament\Resources\Dues\Pages\EditDue;
use App\Filament\Resources\Dues\Pages\ListDues;
use App\Filament\Resources\Dues\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Dues\Schemas\DueForm;
use App\Filament\Resources\Dues\Tables\DuesTable;
use App\Models\Due;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Iuran tambahan (PDH, tabungan jalan-jalan, dll.). Dijaga DuePolicy (due.manage / kas.view_all),
 * dikecualikan dari permission resource Shield.
 */
class DueResource extends Resource
{
    protected static ?string $model = Due::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'BPH';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'iuran tambahan';

    protected static ?string $pluralModelLabel = 'iuran tambahan';

    protected static ?string $navigationLabel = 'Iuran Tambahan';

    protected static ?string $slug = 'iuran-tambahan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DuesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDues::route('/'),
            'create' => CreateDue::route('/create'),
            'edit' => EditDue::route('/{record}/edit'),
        ];
    }
}
