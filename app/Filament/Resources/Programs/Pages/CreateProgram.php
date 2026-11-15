<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProgram extends CreateRecord
{
    protected static string $resource = ProgramResource::class;

    /** Koordinator: divisi selalu divisinya sendiri, apa pun yang dikirim browser. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $restricted = ProgramResource::restrictedDivisionId();

        if ($restricted !== null) {
            $data['division_id'] = $restricted;
        }

        return $data;
    }
}
