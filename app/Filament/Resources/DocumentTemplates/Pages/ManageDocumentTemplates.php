<?php

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentTemplates extends ManageRecords
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah template')->modalWidth('5xl')
                ->using(function (array $data): DocumentTemplate {
                    $template = new DocumentTemplate($data);
                    $template->forceFill(['created_by' => Filament::auth()->id()])->save();

                    return $template;
                }),
        ];
    }
}
