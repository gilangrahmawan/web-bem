<?php

namespace App\Filament\Resources\Programs;

use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\Programs\Schemas\ProgramForm;
use App\Filament\Resources\Programs\Tables\ProgramsTable;
use App\Models\Program;
use App\Models\User;
use App\Policies\ProgramPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Program kerja. Koordinator hanya melihat & mengelola program divisinya;
 * super_admin & pembina melihat semua (pembina read-only).
 */
class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'program kerja';

    protected static ?string $pluralModelLabel = 'program kerja';

    protected static ?string $navigationLabel = 'Program Kerja';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ProgramForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramsTable::configure($table);
    }

    /** @return Builder<Program> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['division', 'period']);
        $divisionId = static::restrictedDivisionId();

        return $divisionId === null ? $query : $query->where('division_id', $divisionId);
    }

    /** Divisi yang membatasi user login (null = semua divisi). */
    public static function restrictedDivisionId(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? ProgramPolicy::restrictedDivisionId($user) : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrograms::route('/'),
            'create' => CreateProgram::route('/create'),
            'edit' => EditProgram::route('/{record}/edit'),
        ];
    }
}
