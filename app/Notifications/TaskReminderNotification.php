<?php

namespace App\Notifications;

use App\Filament\Pages\MyTasks;
use App\Models\EventAssignee;
use App\Models\User;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Pengingat deadline tugas konten H-2 / H-1 (dikirim command tasks:remind). */
class TaskReminderNotification extends Notification
{
    public function __construct(public EventAssignee $assignee, public int $daysLeft) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $event = $this->assignee->event;
        $when = $this->daysLeft === 1 ? 'besok' : "{$this->daysLeft} hari lagi";

        return FilamentNotification::make()
            ->title("Deadline {$when} (H-{$this->daysLeft})")
            ->body("{$event->title} — deadline ".Format::date($event->deadline()).'.')
            ->icon('heroicon-o-clock')
            ->warning()
            ->actions([
                Action::make('open')->label('Buka Tugas Saya')->url(MyTasks::getUrl(panel: 'admin'))->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
