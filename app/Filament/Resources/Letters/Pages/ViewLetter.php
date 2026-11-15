<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Letters\LetterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLetter extends ViewRecord
{
    protected static string $resource = LetterResource::class;

    protected function getHeaderActions(): array
    {
        // Hapus hanya super_admin (AnnouncementPolicy::delete).
        return [DeleteAction::make()->modalDescription('Pesan dan seluruh status bacanya akan dihapus permanen.')];
    }
}
