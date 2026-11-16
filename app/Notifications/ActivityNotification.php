<?php

namespace App\Notifications;

use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Models\StudentActivity;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Notifikasi kegiatan: status proposal/LPJ, pindah penanggung jawab, pengingat tenggat LPJ. */
class ActivityNotification extends Notification
{
    public function __construct(
        public StudentActivity $activity,
        public string $title,
        public string $body,
        public string $kind = 'status',
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-clipboard-document-check')
            ->actions([
                Action::make('open')
                    ->label('Buka kegiatan')
                    ->url(StudentActivityResource::getUrl('view', ['record' => $this->activity], panel: 'admin'))
                    ->markAsRead(),
            ]);

        $this->kind === 'reminder' ? $notification->warning() : $notification->info();

        return [...$notification->getDatabaseMessage(), 'kind' => $this->kind, 'activity_id' => $this->activity->getKey()];
    }
}
