<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Resources\Galleries\GalleryResource;
use App\Models\Gallery;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property Gallery $record
 */
class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;

    /** @var array<int, mixed> */
    protected array $pendingPhotos = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingPhotos = array_values((array) ($data['new_photos'] ?? []));
        unset($data['new_photos']);

        return $data;
    }

    protected function afterCreate(): void
    {
        GalleryResource::attachUploadedPhotos($this->record, $this->pendingPhotos);
        $this->pendingPhotos = [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
