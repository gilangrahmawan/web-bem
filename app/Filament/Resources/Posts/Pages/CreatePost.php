<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\User;
use App\Services\PostContentService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            return app(PostContentService::class)->createDraft($actor, $data);
        } catch (AuthorizationException|ValidationException $e) {
            Notification::make()->title('Konten tidak bisa disimpan')
                ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                ->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft disimpan';
    }

    protected function getRedirectUrl(): string
    {
        return PostResource::getUrl('view', ['record' => $this->record]);
    }
}
