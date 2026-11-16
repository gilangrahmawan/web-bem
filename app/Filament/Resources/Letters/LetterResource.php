<?php

namespace App\Filament\Resources\Letters;

use App\Enums\AnnouncementType;
use App\Filament\Resources\Announcements\AnnouncementResourceBase;
use App\Filament\Resources\Letters\Pages\CreateLetter;
use App\Filament\Resources\Letters\Pages\ListLetters;
use App\Filament\Resources\Letters\Pages\ViewLetter;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Surat & masukan (Humas, letter.create). Surat ber-lampiran otomatis diarsipkan ke Drive "Surat" (DriveLetterArchiver). */
class LetterResource extends AnnouncementResourceBase
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Humas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'surat';

    protected static ?string $pluralModelLabel = 'surat';

    protected static ?string $navigationLabel = 'Surat & Masukan';

    protected static ?string $slug = 'surat';

    public static function type(): AnnouncementType
    {
        return AnnouncementType::Letter;
    }

    public static function canViewAny(): bool
    {
        $user = static::user();

        return (bool) $user && ($user->can('letter.create') || $user->can('report.view'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLetters::route('/'),
            'create' => CreateLetter::route('/create'),
            'view' => ViewLetter::route('/{record}'),
        ];
    }
}
