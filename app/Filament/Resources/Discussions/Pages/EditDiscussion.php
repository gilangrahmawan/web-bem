<?php

namespace App\Filament\Resources\Discussions\Pages;

use App\Filament\Resources\Discussions\DiscussionResource;
use App\Models\Discussion;
use App\Models\User;
use App\Services\DiscussionService;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

/** @property Discussion $record */
class EditDiscussion extends EditRecord
{
    protected static string $resource = DiscussionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(DiscussionService::class)->sanitize($this->actor(), $data, $this->record);
    }

    protected function afterSave(): void
    {
        app(DiscussionService::class)->logSaved($this->actor(), $this->record, created: false);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
