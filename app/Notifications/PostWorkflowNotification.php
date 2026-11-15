<?php

namespace App\Notifications;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/** Notifikasi alur konten (diajukan, disetujui, revisi, ditolak, terbit, diturunkan). */
class PostWorkflowNotification extends Notification
{
    public function __construct(public Post $post, public string $title, public string $body) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-newspaper')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Buka konten')
                    ->url(PostResource::getUrl('view', ['record' => $this->post], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
