<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserAccountService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $temporaryPassword = null;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            [$user, $this->temporaryPassword] = app(UserAccountService::class)->create(
                Auth::user(),
                Arr::except($data, ['roles']),
                array_values($data['roles'] ?? []),
            );
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $user;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function afterCreate(): void
    {
        if ($this->temporaryPassword !== null) {
            UsersTable::notifyTemporaryPassword($this->record, $this->temporaryPassword);
            $this->temporaryPassword = null;
        }
    }
}
