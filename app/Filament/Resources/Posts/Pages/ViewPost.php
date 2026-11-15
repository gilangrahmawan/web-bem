<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\PostWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Detail konten + aksi alur. Tombol tampil sesuai status & izin; PostWorkflowService memeriksa ulang.
 *
 * @property Post $record
 */
class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            $this->flow('submit', 'Ajukan', Heroicon::OutlinedPaperAirplane, 'primary')
                ->visible(fn (): bool => $this->user()->can('submit', $this->record))
                ->requiresConfirmation()
                ->modalDescription('Setelah diajukan, konten tidak bisa diedit sampai dikembalikan untuk revisi.')
                ->action(fn () => $this->run(fn (PostWorkflowService $s) => $s->submit($this->user(), $this->record), 'Konten diajukan')),

            $this->flow('approveCoordinator', 'Setujui (Koordinator)', Heroicon::OutlinedCheck, 'success')
                ->visible(fn (): bool => $this->record->status === PostStatus::PendingCoordinator && $this->canDecide())
                ->schema([$this->note('Catatan (opsional)')])
                ->action(fn (array $data) => $this->run(fn (PostWorkflowService $s) => $s->approveByCoordinator($this->user(), $this->record, $data['note'] ?? null), 'Disetujui, diteruskan ke Ketua/Wakil')),

            $this->flow('approveLeader', 'Setujui (Ketua/Wakil)', Heroicon::OutlinedCheckBadge, 'success')
                ->visible(fn (): bool => $this->record->status === PostStatus::PendingLeader && $this->canDecide())
                ->schema([$this->note('Catatan (opsional)')])
                ->action(fn (array $data) => $this->run(fn (PostWorkflowService $s) => $s->approveByLeader($this->user(), $this->record, $data['note'] ?? null), 'Konten disetujui')),

            $this->flow('requestRevision', 'Minta revisi', Heroicon::OutlinedArrowUturnLeft, 'warning')
                ->visible(fn (): bool => $this->canDecide())
                ->schema([$this->note('Catatan revisi untuk penulis', required: true)])
                ->action(fn (array $data) => $this->run(fn (PostWorkflowService $s) => $s->requestRevision($this->user(), $this->record, $data['note']), 'Dikembalikan ke penulis')),

            $this->flow('reject', 'Tolak', Heroicon::OutlinedXCircle, 'danger')
                ->visible(fn (): bool => $this->canDecide())
                ->schema([$this->note('Alasan penolakan untuk penulis', required: true)])
                ->action(fn (array $data) => $this->run(fn (PostWorkflowService $s) => $s->reject($this->user(), $this->record, $data['note']), 'Konten ditolak')),

            $this->flow('publish', 'Terbitkan', Heroicon::OutlinedGlobeAlt, 'success')
                ->visible(fn (): bool => $this->user()->can('publish', $this->record))
                ->requiresConfirmation()
                ->modalDescription('Konten akan tampil di website publik.')
                ->action(fn () => $this->run(fn (PostWorkflowService $s) => $s->publish($this->user(), $this->record), 'Konten terbit')),

            $this->flow('markSocialPosted', 'Tandai sudah diposting', Heroicon::OutlinedShare, 'gray')
                ->visible(fn (): bool => $this->user()->can('markSocialPosted', $this->record))
                ->fillForm(fn (): array => ['social_post_url' => $this->record->social_post_url])
                ->schema([
                    TextInput::make('social_post_url')->label('Link postingan medsos')->url()->rule('url:http,https')->required()->maxLength(255),
                ])
                ->action(fn (array $data) => $this->run(fn (PostWorkflowService $s) => $s->markSocialPosted($this->user(), $this->record, $data['social_post_url']), 'Ditandai sudah diposting')),

            $this->flow('takeDown', 'Turunkan & revisi', Heroicon::OutlinedArrowDownCircle, 'danger')
                ->visible(fn (): bool => $this->user()->can('takeDown', $this->record))
                ->modalDescription('Konten diturunkan dari website dan dikembalikan ke penulis. Setelah diperbaiki, wajib disetujui ulang.')
                ->schema([$this->note('Alasan penurunan', required: true)])
                ->action(fn (array $data) => $this->run(fn (PostWorkflowService $s) => $s->takeDown($this->user(), $this->record, $data['note']), 'Konten diturunkan untuk revisi')),
        ];
    }

    /** Penyetuju tahap saat ini (bukan penulis). */
    private function canDecide(): bool
    {
        return match ($this->record->status) {
            PostStatus::PendingCoordinator => $this->user()->can('approveCoordinator', $this->record),
            PostStatus::PendingLeader => $this->user()->can('approveLeader', $this->record),
            default => false,
        };
    }

    private function flow(string $name, string $label, Heroicon $icon, string $color): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)->modalSubmitActionLabel($label);
    }

    private function note(string $label, bool $required = false): Textarea
    {
        return Textarea::make('note')->label($label)->rows(3)->maxLength(2000)->required($required)->minLength($required ? 5 : null);
    }

    /** @param  callable(PostWorkflowService): mixed  $callback */
    private function run(callable $callback, string $success): void
    {
        try {
            $callback(app(PostWorkflowService::class));
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
