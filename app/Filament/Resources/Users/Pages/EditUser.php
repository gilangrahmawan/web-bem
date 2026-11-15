<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Tidak ada aksi hapus: user tidak pernah dihapus, hanya dinonaktifkan.
 *
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->getRoleNames()->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $service = app(UserAccountService::class);
        $actor = Auth::user();

        try {
            DB::transaction(function () use ($service, $actor, $record, $data): void {
                $service->update($actor, $record, Arr::except($data, ['roles']));

                if (array_key_exists('roles', $data) && Gate::allows('manageRoles', $record)) {
                    $service->syncRoles($actor, $record, array_values($data['roles'] ?? []));
                }
            });
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $record;
    }
}
