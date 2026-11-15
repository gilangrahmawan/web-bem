<?php

namespace App\Filament\Pages;

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventAssignee;
use App\Models\User;
use App\Services\TaskService;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Tugas konten Medpub (agenda kategori `content`).
 * - Semua pengurus: tugas miliknya (mulai, serahkan hasil, tarik kembali).
 * - Koordinator Medpub / Super Admin: juga melihat semua tugas & me-review (Selesai / Kembalikan).
 * Berkas hasil: JPG/PNG/WebP/PDF/MP4/ZIP ≤ 10 MB, disimpan apa adanya di disk privat (nama UUID).
 */
class MyTasks extends Page implements HasTable
{
    use InteractsWithTable;

    public const RESULT_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
        'application/zip',
        'application/x-zip-compressed',
    ];

    public const RESULT_EXTENSIONS = 'jpg,jpeg,png,webp,pdf,mp4,zip';

    protected static ?string $slug = 'tugas-saya';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Tugas Saya';

    protected static ?string $title = 'Tugas Saya';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.my-tasks';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->is_active
            && ! $user->hasRole(RoleName::Pembina->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $open = $user->taskAssignments()
            ->whereIn('status', [TaskStatus::Todo->value, TaskStatus::InProgress->value])
            ->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Melihat & me-review tugas semua orang. */
    protected function reviewsAll(): bool
    {
        $user = $this->user();

        return $user->isSuperAdmin() || $user->checkPermissionTo('event.assign_task');
    }

    public function table(Table $table): Table
    {
        $user = $this->user();
        $reviewsAll = $this->reviewsAll();

        return $table
            ->query(fn (): Builder => EventAssignee::query()
                ->with(['event', 'user'])
                ->whereHas('event', fn (Builder $q) => $q->where('category', EventCategory::Content->value))
                ->when(! $reviewsAll, fn (Builder $q) => $q->where('user_id', $user->getKey())))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy(
                Event::query()->select('starts_at')->whereColumn('events.id', 'event_assignees.event_id')
            ))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Tugas')
                    ->searchable()
                    ->wrap()
                    ->description(fn (EventAssignee $record): ?string => Str::limit((string) $record->event->description, 80) ?: null),
                TextColumn::make('user.name')
                    ->label('Penanggung jawab')
                    ->visible($reviewsAll),
                TextColumn::make('event.starts_at')
                    ->label('Deadline')
                    ->formatStateUsing(fn (EventAssignee $record): string => Format::date($record->event->deadline()))
                    ->description(fn (EventAssignee $record): ?string => $this->deadlineHint($record))
                    ->color(fn (EventAssignee $record): ?string => $this->isOverdue($record) ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (TaskStatus $state): string => $state->label())
                    ->color(fn (TaskStatus $state): string => $state->color()),
                TextColumn::make('result_original_name')
                    ->label('Hasil')
                    ->placeholder('-')
                    ->state(fn (EventAssignee $record): ?string => $record->result_original_name ?? ($record->result_url ? 'Link hasil' : null))
                    ->icon(fn (EventAssignee $record): ?Heroicon => $record->result_path ? Heroicon::OutlinedPaperClip : ($record->result_url ? Heroicon::OutlinedLink : null))
                    ->url(fn (EventAssignee $record): ?string => $record->result_path
                        ? route('panel.tasks.result', $record)
                        : $record->result_url)
                    ->openUrlInNewTab()
                    ->limit(30),
            ])
            ->filters([
                Filter::make('mine')
                    ->label('Hanya tugas saya')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $q) => $q->where('user_id', $user->getKey()))
                    ->visible($reviewsAll),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TaskStatus::options()),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Mulai kerjakan')
                    ->icon(Heroicon::OutlinedPlay)
                    ->visible(fn (EventAssignee $record): bool => $this->owns($record) && $record->status === TaskStatus::Todo)
                    ->action(fn (EventAssignee $record) => $this->run(fn () => app(TaskService::class)->start($this->user(), $record), 'Status: Dikerjakan')),
                Action::make('submit')
                    ->label('Serahkan hasil')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->visible(fn (EventAssignee $record): bool => $this->owns($record) && $record->status->isOpen())
                    ->modalHeading('Serahkan hasil tugas')
                    ->modalSubmitActionLabel('Serahkan')
                    ->schema([
                        FileUpload::make('result_file')
                            ->label('Berkas hasil')
                            ->disk(EventAssignee::DISK)
                            ->directory(EventAssignee::DIRECTORY)
                            ->visibility('private')
                            ->maxSize(10240)
                            ->acceptedFileTypes(self::RESULT_MIME_TYPES)
                            ->rules(['mimes:'.self::RESULT_EXTENSIONS])
                            ->storeFileNamesIn('result_original_name')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin')
                            )
                            ->helperText('JPG, PNG, WebP, PDF, MP4, atau ZIP, maksimal 10 MB. Disimpan tanpa kompresi.'),
                        TextInput::make('result_url')
                            ->label('Link hasil (opsional)')
                            ->url()
                            ->rule('url:http,https')
                            ->maxLength(500)
                            ->placeholder('https://www.canva.com/…')
                            ->helperText('Misalnya link Canva atau Google Drive. Isi berkas, link, atau keduanya.'),
                    ])
                    ->action(function (EventAssignee $record, array $data): void {
                        $path = $data['result_file'] ?? null;

                        try {
                            $this->run(fn () => app(TaskService::class)->submit(
                                $this->user(),
                                $record,
                                $path,
                                $data['result_original_name'] ?? null,
                                $data['result_url'] ?? null,
                            ), 'Hasil diserahkan');
                        } catch (Halt $halt) {
                            // Berkas yang sudah terunggah dibuang bila penyerahan ditolak.
                            if ($path) {
                                Storage::disk(EventAssignee::DISK)->delete($path);
                            }

                            throw $halt;
                        }
                    }),
                Action::make('withdraw')
                    ->label('Tarik kembali')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Status tugas kembali menjadi Dikerjakan sehingga hasil bisa diperbaiki.')
                    ->visible(fn (EventAssignee $record): bool => $this->owns($record) && $record->status === TaskStatus::Submitted)
                    ->action(fn (EventAssignee $record) => $this->run(fn () => app(TaskService::class)->withdraw($this->user(), $record), 'Penyerahan ditarik kembali')),
                Action::make('markDone')
                    ->label('Tandai selesai')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (EventAssignee $record): bool => $record->status === TaskStatus::Submitted && $this->user()->can('review', $record))
                    ->action(fn (EventAssignee $record) => $this->run(fn () => app(TaskService::class)->review($this->user(), $record, TaskStatus::Done), 'Tugas ditandai selesai')),
                Action::make('sendBack')
                    ->label('Kembalikan')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Status tugas kembali menjadi Dikerjakan agar penanggung jawab memperbaikinya.')
                    ->visible(fn (EventAssignee $record): bool => in_array($record->status, [TaskStatus::Submitted, TaskStatus::Done], true)
                        && ! $this->owns($record)
                        && $this->user()->can('review', $record))
                    ->action(fn (EventAssignee $record) => $this->run(fn () => app(TaskService::class)->review($this->user(), $record, TaskStatus::InProgress), 'Tugas dikembalikan')),
            ])
            ->emptyStateHeading('Belum ada tugas')
            ->emptyStateDescription('Tugas konten yang ditugaskan kepada Anda akan tampil di sini.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck);
    }

    /** Jalankan aksi service; tampilkan pesan Indonesia bila ditolak. */
    protected function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (AuthorizationException) {
            Notification::make()->title('Tidak diizinkan')->danger()->send();

            throw new Halt;
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa diproses')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            throw new Halt;
        }

        Notification::make()->title($successTitle)->success()->send();
    }

    protected function owns(EventAssignee $record): bool
    {
        return $record->user_id === $this->user()->getKey();
    }

    protected function isOverdue(EventAssignee $record): bool
    {
        return $record->status->isOpen() && $record->event->deadline()->copy()->endOfDay()->isPast();
    }

    protected function deadlineHint(EventAssignee $record): ?string
    {
        if (! $record->status->isOpen()) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($record->event->deadline()->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => 'Terlambat '.abs($days).' hari',
            $days === 0 => 'Hari ini',
            default => "H-{$days}",
        };
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
