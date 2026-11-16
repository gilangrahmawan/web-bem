<?php

namespace App\Filament\Pages;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Drive\DocumentService;
use App\Services\Drive\DriveService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Editor dokumen Drive. Auto-save ±10 detik (hanya bila isi berubah), kunci edit 15 menit
 * (diperpanjang tiap simpan; dilepas saat "Selesai"), riwayat versi + pulihkan, export PDF/DOCX.
 * Dibuka user lain saat terkunci → mode baca; editor otomatis tersedia lagi bila kunci dilepas/kedaluwarsa.
 *
 * @property-read Schema $form
 */
class DocumentEditor extends Page
{
    protected static ?string $slug = 'drive/dokumen/{document}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.document-editor';

    #[Locked]
    public int $documentId;

    #[Locked]
    public bool $editing = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $savedAt = null;

    #[Locked]
    public string $savedHash = '';

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'drive-dokumen';
    }

    public static function canAccess(): bool
    {
        return Drive::canAccess();
    }

    public function mount(int $document): void
    {
        $record = Document::query()->find($document);
        abort_if($record === null || Gate::forUser($this->user())->denies('view', $record), 404);

        $this->documentId = $record->getKey();
        $this->editing = app(DocumentService::class)->acquireLock($this->user(), $record);
        $this->fillFrom($record->refresh());
        app(DriveService::class)->recordOpen($this->user(), $record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record()->title;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => ! $this->editing)
            ->components([
                TextInput::make('title')->hiddenLabel()->placeholder('Judul dokumen')->required()->maxLength(200)
                    ->extraInputAttributes(['style' => 'font-size: 1.25rem; font-weight: 700;']),
                RichEditor::make('content')->hiddenLabel()
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'link'],
                        ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                        ['bulletList', 'orderedList', 'blockquote', 'table', 'horizontalRule'],
                        ['undo', 'redo'],
                    ])
                    ->extraInputAttributes(['style' => 'min-height: 60vh;']),
            ]);
    }

    /** Dipanggil wire:poll tiap 10 detik saat mengedit. */
    public function autosave(): void
    {
        if ($this->editing) {
            $this->persist(silent: true);
        }
    }

    /** Dipanggil wire:poll saat mode baca: ambil alih kunci bila sudah bebas. */
    public function checkLock(): void
    {
        if ($this->editing) {
            return;
        }

        $record = $this->record();

        if (app(DocumentService::class)->acquireLock($this->user(), $record)) {
            $this->editing = true;
            $this->fillFrom($record->refresh());
            Notification::make()->title('Dokumen kini bisa Anda edit')->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Simpan')->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->editing)
                ->action(fn () => $this->persist(silent: false)),
            Action::make('finish')->label('Selesai')->icon(Heroicon::OutlinedArrowLeft)->color('gray')
                ->action(function () {
                    if ($this->editing) {
                        $this->persist(silent: true);
                        app(DocumentService::class)->releaseLock($this->user(), $this->record());
                    }

                    $folder = $this->record()->folder_id;

                    return redirect(Drive::getUrl($folder ? ['bagian' => 'folder', 'folder' => $folder] : ['bagian' => 'my']));
                }),
            Action::make('history')->label('Riwayat versi')->icon(Heroicon::OutlinedClock)->color('gray')
                ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')->modalWidth('2xl')
                ->modalContent(fn () => view('filament.pages.partials.document-versions', [
                    'versions' => $this->record()->versions()->with('author:id,name')->limit(50)->get(),
                    'canRestore' => $this->editing,
                ])),
            Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->url(fn (): string => route('panel.drive.documents.export', [$this->documentId, 'pdf'])),
            Action::make('docx')->label('DOCX')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->url(fn (): string => route('panel.drive.documents.export', [$this->documentId, 'docx'])),
        ];
    }

    public function restoreVersion(int $versionId): void
    {
        $version = DocumentVersion::query()->where('document_id', $this->documentId)->find($versionId);

        if (! $version || ! $this->editing) {
            return;
        }

        try {
            $record = app(DocumentService::class)->restoreVersion($this->user(), $this->record(), $version);
        } catch (AuthorizationException|ValidationException $e) {
            $this->failed($e);

            return;
        }

        $this->fillFrom($record);
        $this->unmountAction();
        Notification::make()->title('Versi dipulihkan')->success()->send();
    }

    protected function getViewData(): array
    {
        $record = $this->record()->loadMissing(['locker:id,name', 'editor:id,name', 'folder']);

        return [
            'record' => $record,
            'lockedBy' => $this->editing ? null : $record->locker?->name,
            'canEditAtAll' => Gate::forUser($this->user())->allows('edit', $record) || $record->isLockedFor($this->user()),
            'safeContent' => $record->safeContent(),
            'lockMinutes' => Document::LOCK_MINUTES,
        ];
    }

    private function persist(bool $silent): void
    {
        $state = $this->form->getState();
        $hash = md5(($state['title'] ?? '').'|'.($state['content'] ?? ''));

        if ($silent && $hash === $this->savedHash) {
            // Tidak ada perubahan: cukup perpanjang kunci.
            app(DocumentService::class)->acquireLock($this->user(), $this->record());

            return;
        }

        try {
            app(DocumentService::class)->save($this->user(), $this->record(), (string) ($state['content'] ?? ''), $state['title'] ?? null);
        } catch (AuthorizationException|ValidationException $e) {
            $this->failed($e);

            return;
        }

        $this->savedHash = $hash;
        $this->savedAt = now()->format('H.i.s');

        if (! $silent) {
            Notification::make()->title('Dokumen disimpan')->success()->send();
        }
    }

    private function failed(AuthorizationException|ValidationException $e): void
    {
        $this->editing = false;
        $this->fillFrom($this->record()->refresh());

        Notification::make()->title('Perubahan tidak disimpan')
            ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
            ->danger()->persistent()->send();
    }

    private function fillFrom(Document $record): void
    {
        $this->form->fill(['title' => $record->title, 'content' => $record->safeContent()]);
        $state = ['title' => $record->title, 'content' => $record->safeContent()];
        $this->savedHash = md5($state['title'].'|'.$state['content']);
        $this->savedAt = $record->updated_at ? Format::date($record->updated_at).' '.$record->updated_at->format('H.i') : null;
    }

    private function record(): Document
    {
        $record = Document::query()->find($this->documentId);
        abort_if($record === null || Gate::forUser($this->user())->denies('view', $record), 404);

        return $record;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
