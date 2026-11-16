<?php

namespace App\Filament\Resources\ApplicantAccounts\Pages;

use App\Filament\Resources\ApplicantAccounts\ApplicantAccountResource;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Services\ApplicantAccountService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

/** Role selalu pengaju_kegiatan; password sementara ditampilkan sekali & wajib diganti saat login. */
class CreateApplicantAccount extends CreateRecord
{
    protected static string $resource = ApplicantAccountResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            [$user, $password] = app(ApplicantAccountService::class)->create($actor, $data);
        } catch (AuthorizationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        UsersTable::notifyTemporaryPassword($user, $password);

        return $user;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // Notifikasi password sementara sudah ditampilkan.
    }

    protected function getRedirectUrl(): string
    {
        return ApplicantAccountResource::getUrl('index');
    }
}
