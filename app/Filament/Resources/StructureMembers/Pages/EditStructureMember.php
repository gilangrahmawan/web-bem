<?php

namespace App\Filament\Resources\StructureMembers\Pages;

use App\Filament\Resources\StructureMembers\StructureMemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStructureMember extends EditRecord
{
    protected static string $resource = StructureMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
