<?php

namespace App\Filament\Resources\Letters\Pages;

use App\Filament\Resources\Announcements\Pages\SendsAnnouncement;
use App\Filament\Resources\Letters\LetterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLetter extends CreateRecord
{
    use SendsAnnouncement;

    protected static string $resource = LetterResource::class;

    protected static bool $canCreateAnother = false;
}
