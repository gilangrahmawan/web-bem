<?php

namespace App\Filament\Resources\Galleries\Pages;

use App\Filament\Resources\Galleries\GalleryResource;
use App\Models\Gallery;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Gallery $record
 */
class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    /** @var array<int, mixed> */
    protected array $pendingPhotos = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('Lihat di website')
                ->color('gray')
                ->url(fn (): string => route('public.galleries.show', $this->record->slug))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record->photos()->exists()),
            DeleteAction::make()->modalDescription('Album beserta semua fotonya akan dihapus permanen.'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingPhotos = array_values((array) ($data['new_photos'] ?? []));
        unset($data['new_photos']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingPhotos !== []) {
            GalleryResource::attachUploadedPhotos($this->record, $this->pendingPhotos);
            $this->pendingPhotos = [];

            // Muat ulang form agar foto baru tampil di daftar dan input upload dikosongkan.
            $this->fillForm();
        }
    }
}
