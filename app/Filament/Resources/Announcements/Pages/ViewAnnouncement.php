<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        // Hapus hanya super_admin (AnnouncementPolicy::delete).
        return [DeleteAction::make()->modalDescription('Pesan dan seluruh status bacanya akan dihapus permanen.')];
    }
}
