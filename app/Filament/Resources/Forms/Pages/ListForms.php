<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Filament\Resources\Forms\FormResource;
use App\Models\Form;
use App\Services\Forms\FormBuilderService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListForms extends ListRecords
{
    protected static string $resource = FormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Buat form')->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => FormResource::user()?->can('create', Form::class) ?? false)
                ->schema(FormResource::createSchema())
                ->modalHeading('Buat form baru')
                ->modalSubmitActionLabel('Buat')
                ->action(function (array $data) {
                    abort_unless(FormResource::user()?->can('create', Form::class), 403);
                    $form = app(FormBuilderService::class)->create(FormResource::user(), $data);

                    return redirect(FormBuilder::getUrl(['record' => $form]));
                }),
        ];
    }
}
