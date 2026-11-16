<?php

namespace App\Filament\Pages;

use App\Enums\DriveAccess;
use App\Enums\DriveFileKind;
use App\Enums\DriveGranteeType;
use App\Enums\RoleName;
use App\Models\Contracts\DriveItem;
use App\Models\Division;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DriveFile;
use App\Models\DriveStar;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\User;
use App\Services\Drive\DocumentService;
use App\Services\Drive\DriveAccessResolver;
use App\Services\Drive\DriveBrowser;
use App\Services\Drive\DriveGate;
use App\Services\Drive\DriveService;
use App\Services\Drive\SystemFolders;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Drive internal bergaya Google Drive (AGENTS.md Fase 8). Semua isi lewat DriveBrowser (sudah difilter
 * hak lihat) dan semua aksi lewat DriveService/DocumentService (diperiksa ulang policy).
 * Upload multi-file dikirim SATU PER SATU dari browser (aman untuk post_max_size 25M).
 * Pengaju kegiatan tidak bisa membuka halaman ini.
 */
class Drive extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 'drive';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'BPH';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Drive';

    protected static ?string $title = 'Drive';

    protected string $view = 'filament.pages.drive';

    /** home | my | shared | recent | starred | trash | folder */
    #[Url(as: 'bagian', except: 'home')]
    public string $section = 'home';

    #[Url(as: 'folder')]
    public ?int $folderId = null;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    /** Satu berkas sementara per unggahan (diisi dari JS lewat $wire.upload). */
    public ?TemporaryUploadedFile $upload = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && app(DriveAccessResolver::class)->canUseDrive($user);
    }

    public function mount(): void
    {
        if (app(DriveAccessResolver::class)->isManager($this->user())) {
            app(SystemFolders::class)->letters();
        }

        $this->normalizeLocation();
    }

    public function getHeading(): string
    {
        return '';
    }

    // ---------------------------------------------------------------- navigasi

    public function go(string $section, ?int $folderId = null): void
    {
        $this->section = in_array($section, ['home', 'my', 'shared', 'recent', 'starred', 'trash', 'folder'], true) ? $section : 'home';
        $this->folderId = $this->section === 'folder' ? $folderId : null;
        $this->search = '';
        $this->normalizeLocation();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 100);
    }

    private function normalizeLocation(): void
    {
        if ($this->section !== 'folder') {
            $this->folderId = null;

            return;
        }

        $folder = $this->folderId ? Folder::query()->find($this->folderId) : null;

        if (! $folder || Gate::forUser($this->user())->denies('view', $folder)) {
            $this->section = 'home';
            $this->folderId = null;
        }
    }

    // ---------------------------------------------------------------- upload (dipanggil JS per berkas)

    /** @return array{ok: bool, name?: string, message?: string} */
    public function storeUpload(): array
    {
        $validator = Validator::make(['upload' => $this->upload], [
            'upload' => [
                'required', 'file', 'max:'.DriveService::MAX_FILE_KB,
                'mimes:'.implode(',', DriveFileKind::allowedExtensions()),
                'mimetypes:'.implode(',', DriveFileKind::allowedMimeTypes()),
            ],
        ], [
            'upload.required' => 'Berkas gagal diterima server.',
            'upload.max' => 'Berkas melebihi batas 10 MB.',
            'upload.mimes' => 'Jenis berkas tidak diizinkan.',
            'upload.mimetypes' => 'Isi berkas tidak sesuai jenisnya.',
        ]);

        if ($validator->fails()) {
            $this->upload?->delete();
            $this->upload = null;

            return ['ok' => false, 'message' => $validator->errors()->first()];
        }

        try {
            $file = app(DriveService::class)->upload($this->user(), $this->currentFolderId(), $this->upload);
        } catch (AuthorizationException|ValidationException $e) {
            $this->upload?->delete();
            $this->upload = null;

            return ['ok' => false, 'message' => $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage()];
        }

        $this->upload->delete();
        $this->upload = null;

        return ['ok' => true, 'name' => $file->original_name];
    }

    public function uploadsFinished(int $ok, int $failed): void
    {
        if ($ok > 0) {
            Notification::make()->title("{$ok} berkas diunggah".($failed ? ", {$failed} gagal" : ''))->success()->send();
        }
    }

    // ---------------------------------------------------------------- bintang

    public function toggleStar(string $type, int $id): void
    {
        $item = $this->findItem($type, $id);

        if ($item) {
            app(DriveService::class)->toggleStar($this->user(), $item);
        }
    }

    // ---------------------------------------------------------------- aksi (modal)

    public function createFolderAction(): Action
    {
        return Action::make('createFolder')
            ->label('Folder baru')->icon(Heroicon::OutlinedFolderPlus)
            ->modalWidth('md')->modalSubmitActionLabel('Buat')
            ->authorize(fn (): bool => $this->canCreateHere())
            ->schema([TextInput::make('name')->label('Nama folder')->required()->maxLength(150)->autofocus()])
            ->action(fn (array $data) => $this->run(fn () => app(DriveService::class)
                ->createFolder($this->user(), $this->currentFolderId(), $data['name']), 'Folder dibuat'));
    }

    public function createDocumentAction(): Action
    {
        return Action::make('createDocument')
            ->label('Dokumen baru')->icon(Heroicon::OutlinedDocumentPlus)
            ->modalWidth('md')->modalSubmitActionLabel('Buat & buka')
            ->authorize(fn (): bool => $this->canCreateHere())
            ->schema([
                TextInput::make('title')->label('Judul dokumen')->required()->maxLength(200)->autofocus(),
                Select::make('template_id')->label('Template (opsional)')->placeholder('Dokumen kosong')
                    ->options(fn () => DocumentTemplate::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->action(function (array $data) {
                $document = null;
                $this->run(function () use ($data, &$document) {
                    $document = app(DocumentService::class)->create(
                        $this->user(), $this->currentFolderId(), $data['title'], isset($data['template_id']) ? (int) $data['template_id'] : null,
                    );
                }, null);

                return redirect(DocumentEditor::getUrl(['document' => $document->getKey()]));
            });
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->label('Ganti nama')->icon(Heroicon::OutlinedPencilSquare)->modalWidth('md')
            ->fillForm(fn (array $arguments): array => ['name' => $this->argItem($arguments)?->driveName()])
            ->authorize(fn (array $arguments): bool => ($item = $this->argItem($arguments)) && Gate::forUser($this->user())->allows('update', $item))
            ->schema([TextInput::make('name')->label('Nama baru')->required()->maxLength(255)->autofocus()])
            ->action(fn (array $data, array $arguments) => $this->run(fn () => app(DriveService::class)
                ->rename($this->user(), $this->argItem($arguments), $data['name']), 'Nama diubah'));
    }

    public function moveAction(): Action
    {
        return Action::make('move')
            ->label('Pindahkan')->icon(Heroicon::OutlinedArrowsRightLeft)->modalWidth('lg')
            ->authorize(fn (array $arguments): bool => ($item = $this->argItem($arguments)) && Gate::forUser($this->user())->allows('update', $item))
            ->schema(fn (array $arguments): array => [
                Select::make('target')->label('Pindahkan ke')->required()->searchable()
                    ->options(fn () => app(DriveBrowser::class)->moveTargets(
                        $this->user(), ($item = $this->argItem($arguments)) instanceof Folder ? $item : null,
                    )),
            ])
            ->action(fn (array $data, array $arguments) => $this->run(fn () => app(DriveService::class)->move(
                $this->user(), $this->argItem($arguments), $data['target'] === 'root' ? null : (int) $data['target'],
            ), 'Item dipindahkan'));
    }

    public function shareAction(): Action
    {
        return Action::make('share')
            ->label('Bagikan')->icon(Heroicon::OutlinedUserPlus)->modalWidth('2xl')
            ->modalSubmitActionLabel('Tambahkan akses')
            ->authorize(fn (array $arguments): bool => ($folder = $this->argFolder($arguments)) && Gate::forUser($this->user())->allows('share', $folder))
            ->modalDescription('Akses berlaku untuk folder ini dan seluruh subfoldernya. Hanya untuk pengurus BEM.')
            ->modalContent(fn (array $arguments) => view('filament.pages.partials.drive-share-list', [
                'permissions' => FolderPermission::query()->where('folder_id', $this->argFolder($arguments)?->getKey())->get(),
                'folderId' => $this->argFolder($arguments)?->getKey(),
            ]))
            ->schema([
                Select::make('grantee_type')->label('Bagikan ke')->required()->live()->options(DriveGranteeType::options()),
                Select::make('grantee_id')->label('Penerima')->required()->searchable()
                    ->options(fn (Get $get): array => $this->granteeOptions($get('grantee_type'))),
                Select::make('access')->label('Akses')->required()->default(DriveAccess::View->value)->options(DriveAccess::options()),
            ])
            ->action(fn (array $data, array $arguments) => $this->run(fn () => app(DriveService::class)->share(
                $this->user(), $this->argFolder($arguments), DriveGranteeType::from($data['grantee_type']), (int) $data['grantee_id'], DriveAccess::from($data['access']),
            ), 'Akses ditambahkan'));
    }

    public function unshare(int $permissionId): void
    {
        $permission = FolderPermission::query()->with('folder')->find($permissionId);

        if ($permission) {
            $this->run(fn () => app(DriveService::class)->unshare($this->user(), $permission), 'Akses dicabut');
        }
    }

    public function trashAction(): Action
    {
        return Action::make('trash')
            ->label('Buang ke Sampah')->icon(Heroicon::OutlinedTrash)->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (array $arguments): string => ($this->argItem($arguments) instanceof Folder
                ? 'Folder beserta seluruh isinya dipindah ke Sampah. '
                : '').'Item di Sampah dihapus permanen setelah '.DriveService::trashDays().' hari.')
            ->authorize(fn (array $arguments): bool => ($item = $this->argItem($arguments)) && Gate::forUser($this->user())->allows('delete', $item))
            ->action(fn (array $arguments) => $this->run(fn () => app(DriveService::class)->trash($this->user(), $this->argItem($arguments)), 'Dipindah ke Sampah'));
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Pulihkan')->icon(Heroicon::OutlinedArrowUturnLeft)->color('success')
            ->authorize(fn (array $arguments): bool => ($item = $this->argItem($arguments, true)) && Gate::forUser($this->user())->allows('restore', $item))
            ->action(fn (array $arguments) => $this->run(fn () => app(DriveService::class)->restore($this->user(), $this->argItem($arguments, true)), 'Item dipulihkan'));
    }

    public function forceDeleteAction(): Action
    {
        return Action::make('forceDelete')
            ->label('Hapus permanen')->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->requiresConfirmation()->modalHeading('Hapus permanen?')
            ->modalDescription('Item dan seluruh isinya dihapus selamanya dan tidak bisa dipulihkan.')
            ->authorize(fn (array $arguments): bool => ($item = $this->argItem($arguments, true)) && Gate::forUser($this->user())->allows('forceDelete', $item))
            ->action(fn (array $arguments) => $this->run(fn () => app(DriveService::class)->forceDelete($this->user(), $this->argItem($arguments, true)), 'Dihapus permanen'));
    }

    // ---------------------------------------------------------------- data tampilan

    protected function getViewData(): array
    {
        $user = $this->user();
        $browser = app(DriveBrowser::class);
        $resolver = app(DriveAccessResolver::class);
        $folder = $this->section === 'folder' && $this->folderId ? Folder::query()->find($this->folderId) : null;

        $groups = [];
        $title = '';

        if (trim($this->search) !== '') {
            $title = 'Hasil pencarian "'.$this->search.'"';
            $groups['Hasil'] = $browser->search($user, $this->search);
        } else {
            switch ($this->section) {
                case 'folder':
                    $title = $folder?->name ?? 'Folder';
                    $contents = $browser->folderContents($user, $folder?->getKey());
                    $groups['Folder'] = $contents['folders'];
                    $groups['Berkas'] = $contents['files']->concat($contents['documents']);
                    break;
                case 'my':
                    $title = 'Drive Saya';
                    $contents = $browser->mine($user);
                    $groups['Folder'] = $contents['folders'];
                    $groups['Berkas'] = $contents['files']->concat($contents['documents']);
                    break;
                case 'shared':
                    $title = 'Dibagikan kepada saya';
                    $groups['Folder'] = $browser->shared($user);
                    break;
                case 'recent':
                    $title = 'Terbaru';
                    $groups['Berkas'] = $browser->recent($user, 50);
                    break;
                case 'starred':
                    $title = 'Berbintang';
                    $groups['Item'] = $browser->starred($user);
                    break;
                case 'trash':
                    $title = 'Sampah';
                    $groups['Item'] = $browser->trash($user);
                    break;
                default:
                    $title = 'Selamat datang di Drive';
                    $groups['Folder yang disarankan'] = $browser->suggestedFolders($user);
                    $groups['File terbaru'] = $browser->recent($user, 12);
            }
        }

        $starred = DriveStar::query()->where('user_id', $user->getKey())->get(['item_type', 'item_id'])
            ->map(fn ($s) => $s->item_type->value.':'.$s->item_id)->flip();

        $used = DriveService::usedBytes();
        $quota = DriveService::quotaBytes();

        return [
            'title' => $title,
            'folder' => $folder,
            'breadcrumbs' => $folder ? $browser->breadcrumbs($user, $folder) : collect(),
            'groups' => collect($groups)->map(fn (Collection $items) => $items->map(fn ($item) => $this->present($item, $starred)))->all(),
            'canCreate' => $this->canCreateHere(),
            'isTrash' => $this->section === 'trash' && trim($this->search) === '',
            'isManager' => $resolver->isManager($user),
            'seesAll' => $resolver->seesAll($user),
            'storage' => [
                'used' => Format::bytes($used),
                'quota' => Format::bytes($quota),
                'percent' => min(100, round($used / max(1, $quota) * 100, 1)),
            ],
            'uploadAccept' => '.'.implode(',.', DriveFileKind::allowedExtensions()),
            'maxUploadBytes' => DriveService::MAX_FILE_KB * 1024,
            'trashDays' => DriveService::trashDays(),
            'zipUrl' => $folder ? route('panel.drive.folders.zip', $folder) : null,
            'folderAccessLabel' => $folder ? $resolver->accessFor($user, $folder)?->label() : null,
        ];
    }

    /**
     * @param  Collection<string, int>  $starred
     * @return array<string, mixed>
     */
    private function present(Model&DriveItem $item, Collection $starred): array
    {
        $user = $this->user();
        $type = $item->driveType()->value;
        $gate = Gate::forUser($user);
        $trashed = method_exists($item, 'trashed') && $item->trashed();

        $base = [
            'key' => $type.':'.$item->getKey(),
            'type' => $type,
            'id' => $item->getKey(),
            'name' => $item->driveName(),
            'starred' => $starred->has($type.':'.$item->getKey()),
            'trashed' => $trashed,
            'updated' => Format::date($item->updated_at),
            'deleted' => $trashed ? Format::date($item->getAttribute('deleted_at')) : null,
            'can' => [
                'rename' => ! $trashed && $gate->allows('update', $item),
                'move' => ! $trashed && $gate->allows('update', $item),
                'trash' => ! $trashed && $gate->allows('delete', $item),
                'share' => ! $trashed && $item instanceof Folder && $gate->allows('share', $item),
                'restore' => $trashed && $gate->allows('restore', $item),
                'forceDelete' => $trashed && $gate->allows('forceDelete', $item),
            ],
        ];

        $base['tag'] = match (true) {
            $trashed => 'div',
            $item instanceof Folder => 'button',
            $item instanceof DriveFile && $item->kind->previewable() => 'button',
            default => 'a',
        };

        return match (true) {
            $item instanceof Folder => [...$base, 'icon' => $item->is_system ? 'heroicon-o-lock-closed' : 'heroicon-o-folder', 'system' => $item->is_system, 'meta' => $item->is_system ? 'Folder sistem' : 'Folder'],
            $item instanceof DriveFile => [...$base,
                'icon' => $item->kind->icon(),
                'kindLabel' => $item->kind->label(),
                'color' => $item->kind->chartColor(),
                'meta' => $item->kind->label().' · '.Format::bytes($item->size),
                'owner' => $item->uploader?->name,
                'thumb' => ! $trashed && $item->thumbnail_path ? route('panel.drive.files.thumbnail', $item) : null,
                'preview' => ! $trashed && $item->kind->previewable() ? route('panel.drive.files.preview', $item) : null,
                'previewKind' => $item->kind->value,
                'download' => $trashed ? null : route('panel.drive.files.download', $item),
            ],
            default => [...$base,
                'icon' => DriveFileKind::Document->icon(),
                'color' => DriveFileKind::Document->chartColor(),
                'meta' => 'Dokumen'.($item->editor ? ' · diubah '.$item->editor->name : ''),
                'open' => $trashed ? null : DocumentEditor::getUrl(['document' => $item->getKey()]),
                'download' => $trashed ? null : route('panel.drive.documents.export', [$item, 'pdf']),
            ],
        };
    }

    // ---------------------------------------------------------------- bantuan

    private function canCreateHere(): bool
    {
        if (! in_array($this->section, ['folder', 'my', 'home'], true) || trim($this->search) !== '') {
            return false;
        }

        $folderId = $this->currentFolderId();

        if ($folderId === null && ! app(DriveAccessResolver::class)->isManager($this->user())) {
            return false;
        }

        return app(DriveGate::class)->createIn($this->user(), $folderId);
    }

    private function currentFolderId(): ?int
    {
        return $this->section === 'folder' ? $this->folderId : null;
    }

    /** @return array<int, string> */
    private function granteeOptions(?string $type): array
    {
        return match ($type) {
            DriveGranteeType::Role->value => Role::query()->where('guard_name', 'web')
                ->whereNotIn('name', [RoleName::PengajuKegiatan->value, RoleName::SuperAdmin->value])->orderBy('name')->get()
                ->mapWithKeys(fn (Role $r) => [$r->id => RoleName::labelFor($r->name)])->all(),
            DriveGranteeType::Division->value => Division::query()->orderBy('name')->pluck('name', 'id')->all(),
            DriveGranteeType::User->value => User::query()->where('is_active', true)->whereHas('roles')
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', RoleName::PengajuKegiatan->value))
                ->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    private function argItem(array $arguments, bool $withTrashed = false): (Model&DriveItem)|null
    {
        return $this->findItem((string) ($arguments['type'] ?? ''), (int) ($arguments['id'] ?? 0), $withTrashed);
    }

    private function argFolder(array $arguments): ?Folder
    {
        $item = $this->argItem($arguments);

        return $item instanceof Folder ? $item : null;
    }

    private function findItem(string $type, int $id, bool $withTrashed = false): (Model&DriveItem)|null
    {
        $model = match ($type) {
            'folder' => Folder::class,
            'file' => DriveFile::class,
            'document' => Document::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        $item = $model::withTrashed()->find($id);

        if ($item === null || (! $withTrashed && $item->trashed())) {
            return null;
        }

        return Gate::forUser($this->user())->allows($item->trashed() ? 'restore' : 'view', $item) ? $item : null;
    }

    private function run(callable $callback, ?string $success): void
    {
        try {
            $callback();
        } catch (AuthorizationException $e) {
            Notification::make()->title('Tidak diizinkan')->body($e->getMessage())->danger()->send();

            throw new Halt;
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->first())->danger()->persistent()->send();

            throw new Halt;
        }

        if ($success) {
            Notification::make()->title($success)->success()->send();
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
