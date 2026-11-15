<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Simpan = kirim lewat AnnouncementService (resolve target ke user aktif, notifikasi, log). */
trait SendsAnnouncement
{
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();

        try {
            return app(AnnouncementService::class)->send($actor, static::getResource()::type(), $data);
        } catch (AuthorizationException|ValidationException $e) {
            if (filled($data['attachment_path'] ?? null)) {
                Storage::disk(Announcement::DISK)->delete($data['attachment_path']);
            }

            Notification::make()->title('Gagal mengirim')
                ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : 'Tidak diizinkan.')
                ->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return static::getResource()::type()->label().' terkirim';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
