<?php

namespace App\Filament\Resources\Downloads\Pages;

use App\Filament\Resources\Downloads\DownloadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDownload extends EditRecord
{
    protected static string $resource = DownloadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription('Berkas akan dihapus permanen dari server.'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return DownloadResource::fillFileMetadata($data);
    }
}
