<?php

namespace App\Filament\Resources\StudentActivities\Pages;

use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Models\User;
use App\Services\ActivityWorkflowService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Simpan sebagai draft; pengajuan proposal dari halaman detail. */
class CreateStudentActivity extends CreateRecord
{
    protected static string $resource = StudentActivityResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            return app(ActivityWorkflowService::class)->saveDraft($actor, $data);
        } catch (AuthorizationException|ValidationException $e) {
            Notification::make()->title('Kegiatan tidak bisa disimpan')
                ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                ->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draft kegiatan disimpan. Ajukan proposal dari halaman ini.';
    }

    protected function getRedirectUrl(): string
    {
        return StudentActivityResource::getUrl('view', ['record' => $this->record]);
    }
}
