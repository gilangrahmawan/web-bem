<?php

namespace App\Filament\Resources\StudentActivities\Pages;

use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Models\StudentActivity;
use App\Models\User;
use App\Services\ActivityWorkflowService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Hanya penanggung jawab, hanya saat draft/revisi proposal (StudentActivityPolicy::update). */
class EditStudentActivity extends EditRecord
{
    protected static string $resource = StudentActivityResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            /** @var StudentActivity $record */
            return app(ActivityWorkflowService::class)->saveDraft($actor, $data, $record);
        } catch (AuthorizationException|ValidationException $e) {
            Notification::make()->title('Kegiatan tidak bisa disimpan')
                ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                ->danger()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return StudentActivityResource::getUrl('view', ['record' => $this->record]);
    }
}
