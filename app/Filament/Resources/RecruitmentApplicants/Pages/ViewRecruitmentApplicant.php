<?php

namespace App\Filament\Resources\RecruitmentApplicants\Pages;

use App\Enums\ApplicantStatus;
use App\Filament\Resources\RecruitmentApplicants\RecruitmentApplicantResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Division;
use App\Models\RecruitmentApplicant;
use App\Models\RecruitmentInterview;
use App\Models\User;
use App\Services\RecruitmentService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Detail pendaftar + aksi seleksi. Tombol tampil sesuai status & izin; RecruitmentService memeriksa ulang.
 *
 * @property RecruitmentApplicant $record
 */
class ViewRecruitmentApplicant extends ViewRecord
{
    protected static string $resource = RecruitmentApplicantResource::class;

    protected function getHeaderActions(): array
    {
        $is = fn (ApplicantStatus ...$s): bool => in_array($this->record->status, $s, true);
        $manage = fn (): bool => $this->user()->can('update', $this->record);

        return [
            Action::make('passDocuments')
                ->label('Lolos berkas')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (): bool => $manage() && $is(ApplicantStatus::Registered))
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn (RecruitmentService $s) => $s->passDocuments($this->user(), $this->record), 'Pendaftar lolos berkas')),

            Action::make('scheduleInterview')
                ->label(fn (): string => $this->record->interview_at ? 'Ubah jadwal interview' : 'Jadwalkan interview')
                ->icon(Heroicon::OutlinedCalendarDays)->color('warning')
                ->visible(fn (): bool => $manage() && $is(ApplicantStatus::DocumentPassed, ApplicantStatus::Interview))
                ->modalDescription('Jadwal langsung terlihat oleh pendaftar di halaman cek status.')
                ->fillForm(fn (): array => [
                    'interview_at' => $this->record->interview_at,
                    'interview_location' => $this->record->interview_location,
                ])
                ->schema([
                    DateTimePicker::make('interview_at')->label('Tanggal & jam')->required()->seconds(false)->native(false),
                    TextInput::make('interview_location')->label('Tempat atau link')->required()->maxLength(255)
                        ->placeholder('Sekretariat BEM, Gedung A lt. 2 / https://meet.google.com/…'),
                ])
                ->action(fn (array $data) => $this->run(fn (RecruitmentService $s) => $s->scheduleInterview(
                    $this->user(), $this->record, (string) $data['interview_at'], (string) $data['interview_location'],
                ), 'Jadwal interview disimpan')),

            Action::make('score')
                ->label(fn (): string => $this->ownInterview() ? 'Ubah nilai saya' : 'Beri nilai')
                ->icon(Heroicon::OutlinedStar)->color('info')
                ->visible(fn (): bool => $this->user()->can('score', $this->record))
                ->modalDescription('Nilai & catatan bersifat internal dan tidak pernah ditampilkan ke pendaftar.')
                ->fillForm(fn (): array => [
                    'score' => $this->ownInterview()?->score,
                    'note' => $this->ownInterview()?->note,
                ])
                ->schema([
                    Select::make('score')->label('Nilai')->required()->options([
                        1 => '1 — Kurang', 2 => '2 — Cukup', 3 => '3 — Baik', 4 => '4 — Sangat baik', 5 => '5 — Istimewa',
                    ]),
                    Textarea::make('note')->label('Catatan internal (opsional)')->rows(4)->maxLength(3000),
                ])
                ->action(fn (array $data) => $this->run(fn (RecruitmentService $s) => $s->score(
                    $this->user(), $this->record, (int) $data['score'], $data['note'] ?? null,
                ), 'Nilai disimpan')),

            Action::make('accept')
                ->label('Terima')->icon(Heroicon::OutlinedHandThumbUp)->color('success')
                ->visible(fn (): bool => $manage() && $is(ApplicantStatus::Interview))
                ->modalDescription('Keputusan baru terlihat oleh pendaftar setelah hasil gelombang diumumkan.')
                ->schema([
                    Select::make('accepted_division_id')->label('Diterima di divisi')->required()
                        ->options(fn () => Division::query()->whereIn('id', $this->record->choiceDivisionIds())->pluck('name', 'id'))
                        ->default(fn () => $this->record->first_choice_division_id),
                    Textarea::make('note')->label('Catatan (opsional)')->rows(2)->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->run(fn (RecruitmentService $s) => $s->decide(
                    $this->user(), $this->record, true, (int) $data['accepted_division_id'], $data['note'] ?? null,
                ), 'Pendaftar diterima')),

            Action::make('reject')
                ->label('Tolak')->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible(fn (): bool => $manage() && ! $this->record->status->isFinal())
                ->modalDescription('Keputusan baru terlihat oleh pendaftar setelah hasil gelombang diumumkan.')
                ->schema([Textarea::make('note')->label('Catatan internal (opsional)')->rows(2)->maxLength(1000)])
                ->action(fn (array $data) => $this->run(fn (RecruitmentService $s) => $s->decide(
                    $this->user(), $this->record, false, null, $data['note'] ?? null,
                ), 'Pendaftar ditolak')),

            Action::make('createAccount')
                ->label('Jadikan akun pengurus')->icon(Heroicon::OutlinedUserPlus)->color('primary')
                ->visible(fn (): bool => $manage() && $is(ApplicantStatus::Accepted) && $this->record->user_id === null)
                ->requiresConfirmation()
                ->modalDescription('Akun dibuat NONAKTIF tanpa role, dengan divisi sesuai divisi diterima. Super Admin yang mengaktifkan, mengatur password, dan memberi role di menu User.')
                ->action(function (): void {
                    $this->run(fn (RecruitmentService $s) => $s->createStaffAccount($this->user(), $this->record), 'Akun pengurus dibuat (nonaktif)');
                }),

            Action::make('openAccount')
                ->label('Buka akun di menu User')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->visible(fn (): bool => $this->record->user_id !== null && $this->user()->can('user.manage'))
                ->url(fn (): string => UserResource::getUrl('edit', ['record' => $this->record->user_id])),
        ];
    }

    private function ownInterview(): ?RecruitmentInterview
    {
        return $this->record->interviews()->where('interviewer_id', $this->user()->getKey())->first();
    }

    /** @param  callable(RecruitmentService): mixed  $callback */
    private function run(callable $callback, string $success): void
    {
        try {
            $callback(app(RecruitmentService::class));
        } catch (AuthorizationException $e) {
            Notification::make()->title('Tidak diizinkan')->body($e->getMessage())->danger()->persistent()->send();

            throw new Halt;
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->first())->danger()->persistent()->send();

            throw new Halt;
        }

        $this->record->refresh();
        Notification::make()->title($success)->success()->send();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
