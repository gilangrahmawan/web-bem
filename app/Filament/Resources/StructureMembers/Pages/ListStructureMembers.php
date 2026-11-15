<?php

namespace App\Filament\Resources\StructureMembers\Pages;

use App\Filament\Resources\StructureMembers\StructureMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStructureMembers extends ListRecords
{
    protected static string $resource = StructureMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
