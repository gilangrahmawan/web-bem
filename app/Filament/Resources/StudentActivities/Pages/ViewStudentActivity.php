<?php

namespace App\Filament\Resources\StudentActivities\Pages;

use App\Filament\Resources\StudentActivities\StudentActivityResource;
use App\Models\ActivityPicTransfer;
use App\Models\StudentActivity;
use App\Models\User;
use App\Services\ActivityWorkflowService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Detail kegiatan + aksi alur. Tombol tampil sesuai tahap & izin; ActivityWorkflowService memeriksa ulang.
 *
 * @property StudentActivity $record
 */
class ViewStudentActivity extends ViewRecord
{
    protected static string $resource = StudentActivityResource::class;

    protected function getHeaderActions(): array
    {
        $step = fn (): ?string => $this->record->status->reviewStep();
        $isLpj = fn (): bool => $this->record->status->isLpjStage();

        return [
            EditAction::make(),

            Action::make('submitProposal')
                ->label('Ajukan proposal')->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
                ->visible(fn (): bool => $this->user()->can('submitProposal', $this->record))
                ->requiresConfirmation()
                ->modalDescription('Setelah diajukan, data kegiatan tidak bisa diubah sampai dikembalikan untuk revisi.')
                ->action(function (): void {
                    if ($this->service()->conflictsFor($this->record)->isNotEmpty()) {
                        $this->replaceMountedAction('confirmSubmitProposal');

                        return;
                    }

                    $this->run(fn (ActivityWorkflowService $s) => $s->submitProposal($this->user(), $this->record), 'Proposal diajukan');
                }),

            $this->decision('approve', fn (): string => match ($step()) {
                'secretary' => 'Verifikasi kelengkapan',
                'treasurer' => $isLpj() ? 'Setujui laporan keuangan' : 'Setujui anggaran',
                default => $isLpj() ? 'Setujui LPJ' : 'Setujui proposal',
            }, Heroicon::OutlinedCheckCircle, 'success', required: false),

            $this->decision('revision', fn (): string => 'Minta revisi', Heroicon::OutlinedArrowUturnLeft, 'warning', required: true),

            $this->decision('reject', fn (): string => 'Tolak proposal', Heroicon::OutlinedXCircle, 'danger', required: true)
                ->visible(fn (): bool => $step() === 'leader' && ! $isLpj() && $this->user()->can('review', $this->record)),

            Action::make('submitLpj')
                ->label(fn (): string => $this->record->lpj_path ? 'Unggah ulang LPJ' : 'Unggah LPJ')
                ->icon(Heroicon::OutlinedDocumentArrowUp)->color('primary')
                ->visible(fn (): bool => $this->user()->can('submitLpj', $this->record))
                ->modalDescription(fn (): string => 'Tenggat LPJ: '.Format::date($this->record->lpjDueOn()).'.')
                ->fillForm(fn (): array => ['budget_realization' => $this->record->budget_realization])
                ->schema([
                    FileUpload::make('lpj_path')->label('File LPJ (PDF)')
                        ->disk(StudentActivity::DISK)->directory(StudentActivity::DIRECTORY)->visibility('private')
                        ->maxSize(10240)->acceptedFileTypes(['application/pdf'])->rules(['mimes:pdf'])
                        ->storeFileNamesIn('lpj_name')
                        ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.pdf')
                        ->required(fn (): bool => blank($this->record->lpj_path)),
                    TextInput::make('budget_realization')->label('Realisasi anggaran')->prefix('Rp')->numeric()->integer()->minValue(0)->required(),
                ])
                ->action(fn (array $data) => $this->run(fn (ActivityWorkflowService $s) => $s->submitLpj(
                    $this->user(), $this->record, $data['lpj_path'] ?? null, $data['lpj_name'] ?? null, $data['budget_realization'] ?? null,
                ), 'LPJ diunggah')),

            Action::make('requestPicTransfer')
                ->label('Pindah penanggung jawab')->icon(Heroicon::OutlinedUserGroup)->color('gray')
                ->visible(fn (): bool => $this->user()->can('requestPicTransfer', $this->record) && $this->pendingTransfer() === null)
                ->modalDescription('Hanya ke akun aktif dari organisasi yang sama. Perlu disetujui Sekretaris BEM.')
                ->schema([
                    Select::make('to_user_id')->label('Penanggung jawab baru')->required()->searchable()
                        ->options(fn () => $this->service()->eligiblePics($this->record)->pluck('name', 'id')),
                    Textarea::make('note')->label('Alasan (opsional)')->rows(2)->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->run(fn (ActivityWorkflowService $s) => $s->requestPicTransfer(
                    $this->user(), $this->record, (int) $data['to_user_id'], $data['note'] ?? null,
                ), 'Permintaan pindah penanggung jawab dikirim ke Sekretaris')),

            Action::make('approveTransfer')
                ->label(fn (): string => 'Setujui pindah PJ ke '.($this->pendingTransfer()?->toUser?->name ?? ''))
                ->icon(Heroicon::OutlinedCheck)->color('success')->requiresConfirmation()
                ->visible(fn (): bool => $this->pendingTransfer() !== null && $this->user()->can('decidePicTransfer', $this->record))
                ->action(fn () => $this->run(fn (ActivityWorkflowService $s) => $s->decidePicTransfer($this->user(), $this->pendingTransfer(), true), 'Penanggung jawab dipindahkan')),

            Action::make('rejectTransfer')
                ->label('Tolak pindah PJ')->icon(Heroicon::OutlinedXMark)->color('gray')
                ->visible(fn (): bool => $this->pendingTransfer() !== null && $this->user()->can('decidePicTransfer', $this->record))
                ->schema([Textarea::make('note')->label('Alasan')->rows(2)->maxLength(1000)])
                ->action(fn (array $data) => $this->run(fn (ActivityWorkflowService $s) => $s->decidePicTransfer($this->user(), $this->pendingTransfer(), false, $data['note'] ?? null), 'Permintaan ditolak')),

            Action::make('cancelActivity')
                ->label('Batalkan kegiatan')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->visible(fn (): bool => $this->user()->can('cancel', $this->record) && ! $this->record->status->isFinal())
                ->modalDescription('Kegiatan dibatalkan dan agendanya dihapus dari kalender.')
                ->schema([Textarea::make('reason')->label('Alasan pembatalan')->required()->minLength(5)->rows(3)->maxLength(1000)])
                ->action(fn (array $data) => $this->run(fn (ActivityWorkflowService $s) => $s->cancel($this->user(), $this->record, $data['reason']), 'Kegiatan dibatalkan')),
        ];
    }

    /** Konfirmasi bentrok jadwal sebelum proposal diajukan (boleh tetap diajukan). */
    public function confirmSubmitProposalAction(): Action
    {
        return Action::make('confirmSubmitProposal')
            ->color('warning')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading('Tanggal bentrok dengan agenda lain')
            ->modalDescription('Agenda BEM/UKM berikut berlangsung di tanggal yang sama. Proposal tetap boleh diajukan.')
            ->modalContent(fn () => view('filament.pages.partials.conflict-list', ['conflicts' => $this->service()->conflictsFor($this->record)]))
            ->modalSubmitActionLabel('Tetap ajukan')
            ->modalCancelActionLabel('Batal')
            ->authorize(fn (): bool => $this->user()->can('submitProposal', $this->record))
            ->action(fn () => $this->run(fn (ActivityWorkflowService $s) => $s->submitProposal($this->user(), $this->record), 'Proposal diajukan'));
    }

    private function decision(string $decision, \Closure $label, Heroicon $icon, string $color, bool $required): Action
    {
        return Action::make($decision.'Step')
            ->label($label)->icon($icon)->color($color)->modalSubmitActionLabel($label)
            ->visible(fn (): bool => $this->record->status->reviewStep() !== null && $this->user()->can('review', $this->record))
            ->schema([
                Textarea::make('note')
                    ->label($required ? 'Catatan untuk penanggung jawab' : 'Catatan (opsional)')
                    ->required($required)->minLength($required ? 5 : null)->rows(3)->maxLength(2000),
            ])
            ->action(fn (array $data) => $this->run(fn (ActivityWorkflowService $s) => $s->review($this->user(), $this->record, $decision, $data['note'] ?? null), match ($decision) {
                'approve' => 'Tahap disetujui',
                'revision' => 'Dikembalikan untuk revisi',
                default => 'Proposal ditolak',
            }));
    }

    private function pendingTransfer(): ?ActivityPicTransfer
    {
        return $this->record->picTransfers()->where('status', ActivityPicTransfer::PENDING)->with(['fromUser', 'toUser'])->first();
    }

    /** @param  callable(ActivityWorkflowService): mixed  $callback */
    private function run(callable $callback, string $success): void
    {
        try {
            $callback($this->service());
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

    private function service(): ActivityWorkflowService
    {
        return app(ActivityWorkflowService::class);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
