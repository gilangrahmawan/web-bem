<?php

namespace App\Notifications;

use App\Filament\Pages\MyTasks;
use App\Models\EventAssignee;
use App\Models\User;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Tugas konten baru ditugaskan (AGENTS.md C5). Channel webpush menyusul di Fase 9. */
class TaskAssignedNotification extends Notification
{
    public function __construct(public EventAssignee $assignee) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $event = $this->assignee->event;

        return FilamentNotification::make()
            ->title('Tugas konten baru')
            ->body("Anda ditugaskan: {$event->title}. Deadline ".Format::date($event->deadline()).'.')
            ->icon('heroicon-o-clipboard-document-list')
            ->info()
            ->actions([
                Action::make('open')->label('Buka Tugas Saya')->url(MyTasks::getUrl(panel: 'admin'))->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
