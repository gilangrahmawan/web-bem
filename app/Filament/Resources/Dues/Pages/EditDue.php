<?php

namespace App\Filament\Resources\Dues\Pages;

use App\Filament\Resources\Dues\DueResource;
use App\Models\Due;
use App\Services\ActivityLogger;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDue extends EditRecord
{
    protected static string $resource = DueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Iuran yang sudah punya pembayaran tidak bisa dihapus.')
                ->after(fn (Due $record) => app(ActivityLogger::class)->log('due.deleted', null, [
                    'due_id' => $record->id,
                    'name' => $record->name,
                ])),
        ];
    }

    protected function afterSave(): void
    {
        app(ActivityLogger::class)->log('due.updated', $this->record, [
            'changed' => array_keys($this->record->getChanges()),
        ]);
    }
}
