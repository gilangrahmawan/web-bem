<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Services\ActivityLogger;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOrganizations extends ManageRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah organisasi')
                ->after(fn (Organization $record) => app(ActivityLogger::class)->log('organization.created', $record, ['name' => $record->name])),
        ];
    }
}
