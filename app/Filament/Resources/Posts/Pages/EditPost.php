<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\PostContentService;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Hanya penulis, hanya saat draft/revisi (PostPolicy::update). */
class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            /** @var Post $record */
            return app(PostContentService::class)->updateDraft($actor, $record, $data);
        } catch (AuthorizationException|ValidationException $e) {
            Notification::make()->title('Konten tidak bisa disimpan')
                ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                ->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return PostResource::getUrl('view', ['record' => $this->record]);
    }
}
