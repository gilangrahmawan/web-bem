<?php

namespace App\Filament\Resources\ApplicantAccounts\Pages;

use App\Filament\Resources\ApplicantAccounts\ApplicantAccountResource;
use App\Models\User;
use App\Services\ApplicantAccountService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditApplicantAccount extends EditRecord
{
    protected static string $resource = ApplicantAccountResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            /** @var User $record */
            return app(ApplicantAccountService::class)->update($actor, $record, $data);
        } catch (AuthorizationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return ApplicantAccountResource::getUrl('index');
    }
}
