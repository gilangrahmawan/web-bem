<?php

namespace App\Filament\Resources\Dues\Pages;

use App\Filament\Resources\Dues\DueResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDue extends CreateRecord
{
    protected static string $resource = DueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->log('due.created', $this->record, [
            'name' => $this->record->name,
            'amount' => $this->record->amount,
            'type' => $this->record->type->value,
        ]);
    }
}
