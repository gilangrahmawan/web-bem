<?php

namespace App\Notifications;

use App\Enums\DriveAccess;
use App\Enums\NotificationCategory;
use App\Filament\Pages\Drive;
use App\Models\Folder;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Drive: "Folder dibagikan kepada Anda" (keputusan Fase 9). */
class DriveFolderSharedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        public Folder $folder,
        public string $access,
        public string $sharedBy,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::Drive;
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $access = DriveAccess::tryFrom($this->access)?->label() ?? $this->access;

        return FilamentNotification::make()
            ->title('Folder dibagikan kepada Anda')
            ->body("{$this->sharedBy} membagikan folder \"{$this->folder->name}\" (akses: {$access}).")
            ->icon('heroicon-o-folder')
            ->info()
            ->actions([
                Action::make('open')->label('Buka folder')
                    ->url(Drive::getUrl(['bagian' => 'folder', 'folder' => $this->folder->getKey()], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
