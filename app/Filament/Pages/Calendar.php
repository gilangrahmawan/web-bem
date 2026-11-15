<?php

namespace App\Filament\Pages;

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\Division;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Services\CalendarImportService;
use App\Services\EventConflictService;
use App\Services\EventService;
use App\Support\CalendarFeed;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Kalender dashboard (AGENTS.md C4). Agenda yang tampil dibatasi Event::visibleTo();
 * setiap simpan/ubah/hapus lewat EventService (otorisasi ulang di backend).
 *
 * Bentrok UKM/BEM: saat menyimpan, bila ada agenda lain di tanggal yang sama,
 * data ditahan di properti terkunci lalu modal "Tetap simpan / Batal" ditampilkan.
 */
class Calendar extends Page
{
    protected static ?string $slug = 'kalender';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Kalender';

    protected static ?string $title = 'Kalender';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.calendar';

    /** Data agenda yang menunggu konfirmasi bentrok (tidak bisa diubah dari browser). */
    #[Locked]
    public ?array $pendingEvent = null;

    #[Locked]
    public ?int $pendingEventId = null;

    /** Agenda yang sedang dibuka di modal ubah. */
    #[Locked]
    public ?int $activeEventId = null;

    /**
     * Pratinjau impor CSV yang menunggu konfirmasi: ['file' => string, 'rows' => list<array>].
     * Saat disimpan, setiap baris divalidasi & dicek duplikat ulang di CalendarImportService.
     */
    #[Locked]
    public ?array $importPreview = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('viewAny', Event::class);
    }

    /**
     * Data FullCalendar untuk rentang tanggal; hanya agenda yang boleh dilihat user.
     *
     * @param  list<string>  $categories
     * @return list<array<string, mixed>>
     */
    #[Exposed]
    public function fetchEvents(string $start, string $end, array $categories = [], ?int $divisionId = null): array
    {
        $range = CalendarFeed::parseRange($start, $end);

        if ($range === null) {
            return [];
        }

        $user = $this->user();
        $categoryValues = collect($categories)
            ->map(fn ($value) => EventCategory::tryFrom((string) $value)?->value)
            ->filter()
            ->values()
            ->all();

        return Event::query()
            ->visibleTo($user)
            ->between(...$range)
            ->whereIn('category', $categoryValues)
            ->when($divisionId, fn ($q, int $id) => $q->where('division_id', $id))
            ->with('division')
            ->orderBy('starts_at')
            ->limit(1000)
            ->get()
            ->map(fn (Event $event): array => CalendarFeed::toArray($event, internal: true, extra: [
                'canEdit' => $user->can('update', $event),
            ]))
            ->all();
    }

    /** Dipakai view: kategori yang terlihat untuk filter, divisi, dan izin tambah. */
    protected function getViewData(): array
    {
        return [
            'categories' => EventCategory::cases(),
            'divisions' => Division::query()->orderBy('name')->pluck('name', 'id'),
            'canCreate' => $this->user()->can('create', Event::class),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [$this->importCsvAction(), $this->createEventAction()];
    }

    /** Langkah 1: unggah CSV → validasi → pratinjau (belum menyimpan). */
    public function importCsvAction(): Action
    {
        return Action::make('importCsv')
            ->label('Impor CSV')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->authorize(fn (): bool => $this->user()->can('import', Event::class))
            ->modalHeading('Impor agenda dari CSV')
            ->modalDescription('Untuk kalender akademik, hari peringatan, atau kegiatan BEM/UKM. Libur nasional & cuti bersama tidak diimpor di sini.')
            ->modalSubmitActionLabel('Periksa file')
            ->schema([
                FileUpload::make('file')
                    ->label('File CSV')
                    ->required()
                    ->storeFiles(false)
                    ->maxSize(1024)
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->rules(['mimes:csv,txt'])
                    ->helperText(new HtmlString(
                        'Maksimal 1 MB, UTF-8, pemisah koma atau titik koma. Header wajib: '
                        .'<code>'.e(implode(',', CalendarImportService::COLUMNS)).'</code>. '
                        .'Tanggal YYYY-MM-DD; end_date boleh kosong. Kategori: '
                        .e(implode(', ', array_map(fn (EventCategory $c) => $c->value, EventCategory::importableCases()))).'.'
                    )),
            ])
            ->action(function (array $data): void {
                $file = Arr::first(Arr::wrap($data['file'] ?? null));

                if (! $file instanceof TemporaryUploadedFile) {
                    Notification::make()->title('File tidak terbaca')->danger()->send();

                    throw new Halt;
                }

                try {
                    $rows = app(CalendarImportService::class)->parse((string) $file->get());
                } catch (ValidationException $e) {
                    Notification::make()->title('File CSV tidak valid')
                        ->body(collect($e->errors())->flatten()->first())
                        ->danger()
                        ->persistent()
                        ->send();

                    throw new Halt;
                } finally {
                    $file->delete(); // file tidak disimpan
                }

                $this->importPreview = [
                    'file' => Str::limit($file->getClientOriginalName(), 150, ''),
                    'rows' => $rows,
                ];

                $this->replaceMountedAction('confirmImport');
            });
    }

    /** Langkah 2: pratinjau + jumlah valid/duplikat/tidak valid → simpan baris valid. */
    public function confirmImportAction(): Action
    {
        return Action::make('confirmImport')
            ->authorize(fn (): bool => $this->user()->can('import', Event::class))
            ->modalHeading(fn (): string => 'Pratinjau impor — '.($this->importPreview['file'] ?? 'CSV'))
            ->modalWidth('5xl')
            ->modalContent(fn () => view('filament.pages.partials.import-preview', [
                'rows' => $this->importPreview['rows'] ?? [],
                'counts' => CalendarImportService::counts($this->importPreview['rows'] ?? []),
            ]))
            ->modalSubmitAction(function (Action $action): Action|false {
                $valid = CalendarImportService::counts($this->importPreview['rows'] ?? [])['valid'];

                return $valid > 0 ? $action->label("Simpan {$valid} agenda") : false;
            })
            ->modalCancelActionLabel('Batal')
            ->action(function (): void {
                $preview = $this->importPreview;
                $this->importPreview = null;

                if ($preview === null) {
                    return;
                }

                try {
                    $summary = app(CalendarImportService::class)->import($this->user(), $preview['rows'], $preview['file']);
                } catch (AuthorizationException) {
                    Notification::make()->title('Tidak diizinkan mengimpor agenda')->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("{$summary['created']} agenda diimpor")
                    ->body("Duplikat dilewati: {$summary['duplicate']} · Tidak valid: {$summary['invalid']}")
                    ->success()
                    ->send();

                $this->dispatch('bem-calendar-refresh');
            });
    }

    public function createEventAction(): Action
    {
        return Action::make('createEvent')
            ->label('Tambah agenda')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => $this->user()->can('create', Event::class))
            ->modalHeading('Tambah agenda')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('2xl')
            ->schema(fn (): array => $this->eventFormSchema())
            ->fillForm(fn (array $arguments): array => $this->defaultsFromSelection($arguments))
            ->action(fn (array $data) => $this->attemptSave($data, null));
    }

    public function editEventAction(): Action
    {
        return Action::make('editEvent')
            ->modalHeading('Ubah agenda')
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('2xl')
            ->authorize(fn (array $arguments): bool => ($event = $this->findEvent($arguments['id'] ?? null)) !== null
                && $this->user()->can('update', $event))
            ->schema(fn (): array => $this->eventFormSchema())
            ->fillForm(function (array $arguments): array {
                $event = $this->findEvent($arguments['id'] ?? null);
                $this->activeEventId = $event?->getKey();

                return $event ? $this->formDataFrom($event) : [];
            })
            ->action(function (array $data): void {
                $event = $this->findEvent($this->activeEventId);

                if ($event === null) {
                    return;
                }

                $this->attemptSave($data, $event);
            })
            ->extraModalFooterActions([
                Action::make('deleteEvent')
                    ->label('Hapus')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->modalHeading('Hapus agenda ini?')
                    ->modalDescription('Agenda dan data penanggung jawabnya akan dihapus permanen.')
                    ->modalSubmitActionLabel('Ya, hapus')
                    ->action(function (): void {
                        $this->deleteActiveEvent();
                    })
                    ->cancelParentActions(),
            ]);
    }

    public function viewEventAction(): Action
    {
        return Action::make('viewEvent')
            ->authorize(fn (array $arguments): bool => ($event = $this->findEvent($arguments['id'] ?? null)) !== null
                && $this->user()->can('view', $event))
            ->modalHeading(fn (array $arguments): string => $this->findEvent($arguments['id'] ?? null)?->title ?? 'Agenda')
            ->modalContent(fn (array $arguments) => view('filament.pages.partials.event-detail', [
                'event' => $this->findEvent($arguments['id'] ?? null)?->load(['division', 'assignees.user']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }

    public function confirmConflictAction(): Action
    {
        return Action::make('confirmConflict')
            ->color('warning')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading('Tanggal bentrok dengan agenda lain')
            ->modalDescription('Agenda UKM/BEM berikut berlangsung di tanggal yang sama. Agenda tetap boleh disimpan dan akan ditandai ⚠ di kalender.')
            ->modalContent(fn () => view('filament.pages.partials.conflict-list', [
                'conflicts' => $this->pendingConflicts(),
            ]))
            ->modalSubmitActionLabel('Tetap simpan')
            ->modalCancelActionLabel('Batal')
            ->action(function (): void {
                if ($this->pendingEvent === null) {
                    return;
                }

                $data = $this->pendingEvent;
                $event = $this->pendingEventId ? $this->findEvent($this->pendingEventId) : null;
                $this->clearPending();

                $this->persist($data, $event);
            })
            ->after(fn () => $this->clearPending());
    }

    /**
     * Simpan langsung, atau tahan dulu dan minta konfirmasi bila tanggalnya bentrok.
     *
     * @param  array<string, mixed>  $data
     */
    protected function attemptSave(array $data, ?Event $event): void
    {
        $category = EventCategory::tryFrom((string) ($data['category'] ?? ''));

        if ($category?->checksConflict() && filled($data['starts_at'] ?? null)) {
            $conflicts = app(EventConflictService::class)->findConflicts(
                $category,
                Carbon::parse($data['starts_at']),
                filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
                $event?->getKey(),
            );

            if ($conflicts->isNotEmpty()) {
                $this->pendingEvent = $data;
                $this->pendingEventId = $event?->getKey();
                $this->replaceMountedAction('confirmConflict');

                return;
            }
        }

        $this->persist($data, $event);
    }

    /** @param  array<string, mixed>  $data */
    protected function persist(array $data, ?Event $event): void
    {
        $service = app(EventService::class);

        try {
            $event
                ? $service->update($this->user(), $event, $data)
                : $service->create($this->user(), $data);
        } catch (AuthorizationException $e) {
            Notification::make()->title('Tidak diizinkan')->body($e->getMessage())->danger()->send();

            throw new Halt;
        } catch (ValidationException $e) {
            Notification::make()->title('Data agenda belum valid')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            throw new Halt;
        }

        Notification::make()->title($event ? 'Agenda diperbarui' : 'Agenda ditambahkan')->success()->send();
        $this->dispatch('bem-calendar-refresh');
    }

    protected function deleteActiveEvent(): void
    {
        $event = $this->findEvent($this->activeEventId);

        if ($event === null) {
            return;
        }

        try {
            app(EventService::class)->delete($this->user(), $event);
        } catch (AuthorizationException) {
            Notification::make()->title('Tidak diizinkan menghapus agenda ini')->danger()->send();

            return;
        }

        $this->activeEventId = null;
        Notification::make()->title('Agenda dihapus')->success()->send();
        $this->dispatch('bem-calendar-refresh');
    }

    /** @return array<int, mixed> */
    protected function eventFormSchema(): array
    {
        $user = $this->user();
        $categoryOf = fn (Get $get): ?EventCategory => EventCategory::tryFrom((string) $get('category'));

        return [
            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(200),
            Grid::make(2)->schema([
                Select::make('category')
                    ->label('Kategori')
                    ->options(collect(EventPolicy::creatableCategories($user))
                        ->mapWithKeys(fn (EventCategory $c): array => [$c->value => $c->label()]))
                    ->required()
                    ->live(),
                Select::make('division_id')
                    ->label('Divisi')
                    ->options(fn () => Division::query()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Umum (semua pengurus)')
                    ->helperText('Kosongkan untuk agenda umum.')
                    ->visible(fn (Get $get): bool => (bool) $categoryOf($get)?->isDivisionScoped()
                        && EventPolicy::managesAllDivisions($user)),
            ]),
            Toggle::make('all_day')
                ->label('Seharian')
                ->default(true)
                ->live()
                ->hidden(fn (Get $get): bool => $categoryOf($get) === EventCategory::Content),
            Grid::make(2)->schema([
                DateTimePicker::make('starts_at')
                    ->label(fn (Get $get): string => $categoryOf($get) === EventCategory::Content ? 'Deadline' : 'Mulai')
                    ->required()
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat(fn (Get $get): string => $get('all_day') ? 'j F Y' : 'j F Y, H:i')
                    ->time(fn (Get $get): bool => ! $get('all_day')),
                DateTimePicker::make('ends_at')
                    ->label('Selesai')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat(fn (Get $get): string => $get('all_day') ? 'j F Y' : 'j F Y, H:i')
                    ->time(fn (Get $get): bool => ! $get('all_day'))
                    ->afterOrEqual('starts_at')
                    ->helperText('Opsional, untuk agenda lebih dari satu hari.')
                    ->hidden(fn (Get $get): bool => $categoryOf($get) === EventCategory::Content),
            ]),
            Select::make('assignee_ids')
                ->label('Penanggung jawab')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn () => User::query()->active()->withoutRole(RoleName::Pembina->value)->orderBy('name')->pluck('name', 'id'))
                ->required(fn (Get $get): bool => $categoryOf($get) === EventCategory::Content)
                ->visible(fn (Get $get): bool => $categoryOf($get) === EventCategory::Content)
                ->helperText('Mendapat notifikasi saat ditugaskan, serta pengingat H-2 dan H-1.'),
            Grid::make(2)->schema([
                TextInput::make('location')
                    ->label('Lokasi')
                    ->maxLength(200),
                TextInput::make('organizer')
                    ->label('Penyelenggara')
                    ->maxLength(150)
                    ->visible(fn (Get $get): bool => (bool) $categoryOf($get)?->isPublic()),
            ]),
            TextInput::make('registration_url')
                ->label('Link pendaftaran')
                ->url()
                ->rule('url:http,https')
                ->maxLength(500)
                ->visible(fn (Get $get): bool => (bool) $categoryOf($get)?->isPublic()),
            Textarea::make('description')
                ->label('Keterangan')
                ->rows(3)
                ->maxLength(5000),
        ];
    }

    /**
     * Nilai awal form dari tanggal yang diklik/diseret di kalender.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function defaultsFromSelection(array $arguments): array
    {
        $categories = EventPolicy::creatableCategories($this->user());
        $defaults = [
            'all_day' => true,
            'category' => count($categories) === 1 ? $categories[0]->value : null,
        ];

        try {
            $start = isset($arguments['start']) ? Carbon::parse((string) $arguments['start']) : null;
            $end = isset($arguments['end']) ? Carbon::parse((string) $arguments['end']) : null;
        } catch (\Throwable) {
            return $defaults;
        }

        if ($start === null) {
            return $defaults;
        }

        $allDay = (bool) ($arguments['allDay'] ?? true);

        if ($allDay && $end !== null) {
            $end = $end->subDay(); // akhir eksklusif FullCalendar → inklusif
        }

        // Satu hari penuh tidak perlu tanggal selesai.
        $keepEnd = $end !== null && (! $allDay || ! $end->isSameDay($start));

        return [
            ...$defaults,
            'all_day' => $allDay,
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $keepEnd ? $end->format('Y-m-d H:i:s') : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function formDataFrom(Event $event): array
    {
        return [
            'title' => $event->title,
            'category' => $event->category->value,
            'division_id' => $event->division_id,
            'all_day' => $event->all_day,
            'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $event->ends_at?->format('Y-m-d H:i:s'),
            'assignee_ids' => $event->assignees()->pluck('user_id')->all(),
            'location' => $event->location,
            'organizer' => $event->organizer,
            'registration_url' => $event->registration_url,
            'description' => $event->description,
        ];
    }

    /** @return Collection<int, Event> */
    protected function pendingConflicts(): Collection
    {
        $data = $this->pendingEvent;
        $category = EventCategory::tryFrom((string) ($data['category'] ?? ''));

        if ($data === null || $category === null || blank($data['starts_at'] ?? null)) {
            return new Collection;
        }

        return app(EventConflictService::class)->findConflicts(
            $category,
            Carbon::parse($data['starts_at']),
            filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at']) : null,
            $this->pendingEventId,
        );
    }

    protected function clearPending(): void
    {
        $this->pendingEvent = null;
        $this->pendingEventId = null;
    }

    protected function findEvent(mixed $id): ?Event
    {
        if (! is_numeric($id)) {
            return null;
        }

        return Event::query()->visibleTo($this->user())->find((int) $id);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
