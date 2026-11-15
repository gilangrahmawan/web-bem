<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\AnnouncementType;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Pengumuman (Sekretaris, announcement.create). */
class AnnouncementResource extends AnnouncementResourceBase
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'pengumuman';

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $slug = 'pengumuman';

    public static function type(): AnnouncementType
    {
        return AnnouncementType::Announcement;
    }

    public static function canViewAny(): bool
    {
        $user = static::user();

        return (bool) $user && ($user->can('announcement.create') || $user->can('report.view'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'view' => ViewAnnouncement::route('/{record}'),
        ];
    }
}
