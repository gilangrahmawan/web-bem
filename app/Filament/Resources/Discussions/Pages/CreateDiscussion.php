<?php

namespace App\Filament\Resources\Discussions\Pages;

use App\Filament\Resources\Discussions\DiscussionResource;
use App\Models\User;
use App\Services\DiscussionService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscussion extends CreateRecord
{
    protected static string $resource = DiscussionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(DiscussionService::class)->sanitize($this->actor(), $data);
    }

    protected function afterCreate(): void
    {
        app(DiscussionService::class)->logSaved($this->actor(), $this->record, created: true);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
