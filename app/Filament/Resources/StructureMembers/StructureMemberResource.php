<?php

namespace App\Filament\Resources\StructureMembers;

use App\Filament\Resources\StructureMembers\Pages\CreateStructureMember;
use App\Filament\Resources\StructureMembers\Pages\EditStructureMember;
use App\Filament\Resources\StructureMembers\Pages\ListStructureMembers;
use App\Filament\Resources\StructureMembers\Schemas\StructureMemberForm;
use App\Filament\Resources\StructureMembers\Tables\StructureMembersTable;
use App\Models\StructureMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Data struktur kepengurusan per periode untuk halaman publik /struktur. */
class StructureMemberResource extends Resource
{
    protected static ?string $model = StructureMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'anggota struktur';

    protected static ?string $pluralModelLabel = 'struktur kepengurusan';

    protected static ?string $navigationLabel = 'Struktur Kepengurusan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return StructureMemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StructureMembersTable::configure($table);
    }

    /** @return Builder<StructureMember> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['division', 'period']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStructureMembers::route('/'),
            'create' => CreateStructureMember::route('/create'),
            'edit' => EditStructureMember::route('/{record}/edit'),
        ];
    }
}
