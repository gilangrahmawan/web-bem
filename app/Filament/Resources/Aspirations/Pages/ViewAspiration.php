<?php

namespace App\Filament\Resources\Aspirations\Pages;

use App\Enums\AspirationStatus;
use App\Filament\Resources\Aspirations\AspirationResource;
use App\Models\Aspiration;
use App\Models\Discussion;
use App\Models\User;
use App\Services\AspirationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Detail aspirasi + aksi alur. Tombol hanya tampil pada status & izin yang sesuai;
 * AspirationService tetap memeriksa ulang izin dan status.
 *
 * @property Aspiration $record
 */
class ViewAspiration extends ViewRecord
{
    protected static string $resource = AspirationResource::class;

    public const REJECTION_PLACEHOLDER = 'Mohon maaf, aspirasi ini berada di luar kewenangan BEM. Silakan sampaikan langsung ke '
        .'unit terkait di kampus, misalnya Biro Administrasi Akademik (BAA), atau melalui layanan pengaduan resmi kampus. Terima kasih atas kepedulian Anda.';

    protected function getHeaderActions(): array
    {
        return [
            $this->flowAction('review', 'Mulai tinjau', Heroicon::OutlinedEye, 'primary')
                ->visible(fn (): bool => $this->is(AspirationStatus::New) && $this->user()->can('handle', $this->record))
                ->schema([$this->internalNote()])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->review($this->user(), $this->record, $data['note'] ?? null), 'Aspirasi sedang ditinjau')),

            $this->flowAction('respond', 'Tulis tanggapan', Heroicon::OutlinedChatBubbleLeftEllipsis, 'gray')
                ->visible(fn (): bool => $this->is(AspirationStatus::InReview, AspirationStatus::Returned) && $this->user()->can('handle', $this->record))
                ->fillForm(fn (): array => ['response' => $this->record->response])
                ->schema([$this->responseField()])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->respond($this->user(), $this->record, $data['response']), 'Tanggapan disimpan')),

            $this->flowAction('resolve', 'Selesaikan', Heroicon::OutlinedCheckCircle, 'success')
                ->visible(fn (): bool => $this->is(AspirationStatus::InReview, AspirationStatus::Returned) && $this->user()->can('handle', $this->record))
                ->modalDescription('Tanggapan akan tampil ke pengirim di halaman cek status.')
                ->fillForm(fn (): array => ['response' => $this->record->response])
                ->schema([$this->responseField(), $this->internalNote()])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->resolve($this->user(), $this->record, $data['response'], $data['note'] ?? null), 'Aspirasi selesai')),

            $this->flowAction('escalate', 'Eskalasi ke Ketua/Wakil', Heroicon::OutlinedArrowUpCircle, 'warning')
                ->visible(fn (): bool => $this->is(AspirationStatus::InReview) && $this->user()->can('escalate', $this->record))
                ->schema([
                    Textarea::make('note')->label('Alasan eskalasi (internal)')->required()->rows(3)->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->escalate($this->user(), $this->record, $data['note']), 'Aspirasi dieskalasi')),

            $this->flowAction('forward', 'Teruskan ke Kajian', Heroicon::OutlinedArrowRightCircle, 'primary')
                ->visible(fn (): bool => $this->is(AspirationStatus::Escalated) && $this->user()->can('forward', $this->record))
                ->schema([$this->internalNote('Arahan untuk Divisi Kajian (internal)')])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->forwardToKajian($this->user(), $this->record, $data['note'] ?? null), 'Diteruskan ke Divisi Kajian')),

            $this->flowAction('submitStudy', 'Kirim hasil kajian', Heroicon::OutlinedDocumentCheck, 'success')
                ->visible(fn (): bool => $this->is(AspirationStatus::InStudy) && $this->user()->can('study', $this->record))
                ->modalDescription('Hasil kajian dikembalikan ke Humas dan otomatis tercatat sebagai entri Pembahasan Kajian.')
                ->schema([
                    Textarea::make('study')->label('Hasil kajian')->required()->rows(6)->maxLength(10000),
                    Select::make('discussion_id')
                        ->label('Tautkan ke pembahasan yang sudah ada (opsional)')
                        ->options(fn () => Discussion::query()->latest()->limit(100)->pluck('title', 'id'))
                        ->searchable()
                        ->helperText('Kosongkan untuk membuat entri pembahasan baru.'),
                ])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->submitStudy(
                    $this->user(),
                    $this->record,
                    $data['study'],
                    filled($data['discussion_id'] ?? null) ? (int) $data['discussion_id'] : null,
                ), 'Hasil kajian dikirim ke Humas')),

            $this->flowAction('reject', 'Tolak', Heroicon::OutlinedXCircle, 'danger')
                ->visible(fn (): bool => ($this->is(AspirationStatus::New, AspirationStatus::InReview) && $this->user()->can('handle', $this->record))
                    || ($this->is(AspirationStatus::Escalated) && $this->user()->can('forward', $this->record)))
                ->modalDescription('Alasan penolakan akan DITAMPILKAN ke pengirim di halaman cek status. Gunakan bahasa yang sopan.')
                ->schema([
                    Textarea::make('rejection_reason')
                        ->label('Alasan penolakan (tampil ke pengirim)')
                        ->placeholder(self::REJECTION_PLACEHOLDER)
                        ->required()
                        ->minLength(AspirationService::MIN_REASON)
                        ->maxLength(2000)
                        ->rows(4),
                    $this->internalNote(),
                ])
                ->action(fn (array $data) => $this->run(fn (AspirationService $s) => $s->reject($this->user(), $this->record, $data['rejection_reason'], $data['note'] ?? null), 'Aspirasi ditolak')),
        ];
    }

    private function flowAction(string $name, string $label, Heroicon $icon, string $color): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)->modalSubmitActionLabel($label);
    }

    private function internalNote(string $label = 'Catatan internal (tidak tampil ke pengirim)'): Textarea
    {
        return Textarea::make('note')->label($label)->rows(2)->maxLength(2000);
    }

    private function responseField(): Textarea
    {
        return Textarea::make('response')
            ->label('Tanggapan untuk pengirim (tampil di cek status)')
            ->required()
            ->rows(5)
            ->maxLength(5000);
    }

    private function is(AspirationStatus ...$statuses): bool
    {
        return in_array($this->record->status, $statuses, true);
    }

    /** @param  callable(AspirationService): mixed  $callback */
    private function run(callable $callback, string $success): void
    {
        try {
            $callback(app(AspirationService::class));
        } catch (AuthorizationException) {
            Notification::make()->title('Tidak diizinkan')->danger()->send();

            throw new Halt;
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->first())->danger()->send();

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
