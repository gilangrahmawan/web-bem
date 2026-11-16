<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\Resources\RecruitmentApplicants\RecruitmentApplicantResource;
use App\Models\RecruitmentApplicant;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Oprec: pendaftar baru → Sekretaris & koordinator divisi pilihan 1/2 (keputusan Fase 9). */
class RecruitmentApplicantRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(public RecruitmentApplicant $applicant) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function pushCategory(): NotificationCategory
    {
        return NotificationCategory::Recruitment;
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        $a = $this->applicant->loadMissing(['firstChoice', 'secondChoice']);
        $choices = collect([$a->firstChoice?->name, $a->secondChoice?->name])->filter()->implode(' / ');

        return FilamentNotification::make()
            ->title('Pendaftar oprec baru')
            ->body("{$a->name} ({$a->prodi} {$a->batch_year}) memilih {$choices}.")
            ->icon('heroicon-o-user-plus')
            ->info()
            ->actions([
                Action::make('open')->label('Lihat pendaftar')
                    ->url(RecruitmentApplicantResource::getUrl('view', ['record' => $a], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
