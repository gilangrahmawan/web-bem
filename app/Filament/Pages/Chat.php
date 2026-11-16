<?php

namespace App\Filament\Pages;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationSyncService;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Chat (AGENTS.md C6 + Fase 9). Hanya percakapan milik user yang tampil; semua aksi lewat ChatService
 * (diperiksa ConversationPolicy, berlaku juga untuk Super Admin). Pembina tidak bisa membuka halaman ini.
 * Real-time: sinyal Pusher → refresh; tanpa Pusher / terputus → polling 5 detik.
 */
class Chat extends Page
{
    use WithFileUploads;

    public const PAGE = 50;

    protected static ?string $slug = 'chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $title = 'Chat';

    protected string $view = 'filament.pages.chat';

    #[Url(as: 'percakapan')]
    public ?int $conversationId = null;

    public string $search = '';

    public string $messageSearch = '';

    public string $body = '';

    public ?TemporaryUploadedFile $attachment = null;

    public ?int $replyToId = null;

    public int $limit = self::PAGE;

    /** true = polling 5 detik (Pusher tidak tersedia/terputus). */
    public bool $polling = true;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canUseChat();
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->canUseChat()) {
            return null;
        }

        $count = app(ChatService::class)->unreadCount($user);

        return $count > 0 ? ($count > 99 ? '99+' : (string) $count) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        app(ConversationSyncService::class)->syncUser($this->user());

        if ($this->conversationId !== null) {
            $this->open($this->conversationId);
        }
    }

    public function getHeading(): string
    {
        return '';
    }

    // ---------------------------------------------------------------- real-time & polling

    #[On('chat-realtime')]
    public function setRealtime(bool $connected = false): void
    {
        $this->polling = ! $connected;
    }

    #[On('chat-changed')]
    public function onChatChanged(int $conversation_id = 0): void
    {
        if ($conversation_id === $this->conversationId) {
            $this->markCurrentRead();
        }
    }

    public function poll(): void
    {
        $this->markCurrentRead();
    }

    // ---------------------------------------------------------------- navigasi

    public function open(int $id): void
    {
        $conversation = Conversation::query()->find($id);

        if (! $conversation || Gate::forUser($this->user())->denies('view', $conversation)) {
            $this->conversationId = null;
            Notification::make()->title('Percakapan tidak ditemukan')->danger()->send();

            return;
        }

        $this->conversationId = $conversation->getKey();
        $this->resetComposer();
        $this->limit = self::PAGE;
        $this->messageSearch = '';
        $this->markCurrentRead();
    }

    public function close(): void
    {
        $this->conversationId = null;
        $this->resetComposer();
    }

    public function loadMore(): void
    {
        $this->limit = min($this->limit + self::PAGE, 1000);
    }

    // ---------------------------------------------------------------- pesan

    public function send(): void
    {
        $conversation = $this->current();

        if (! $conversation) {
            return;
        }

        $this->validate([
            'body' => ['nullable', 'string', 'max:'.ChatService::MAX_BODY],
            'attachment' => ['nullable', 'file', 'max:'.ChatService::FILE_MAX_KB],
        ], ['attachment.max' => 'Lampiran maksimal 10 MB.']);

        try {
            app(ChatService::class)->send($this->user(), $conversation, $this->body, $this->attachment, $this->replyToId);
        } catch (AuthorizationException|ValidationException $e) {
            $this->fail($e);

            return;
        }

        $this->attachment?->delete();
        $this->resetComposer();
        $this->dispatch('chat-sent');
    }

    public function replyTo(int $messageId): void
    {
        $this->replyToId = Message::query()->where('conversation_id', $this->conversationId)->whereKey($messageId)->exists() ? $messageId : null;
    }

    public function cancelReply(): void
    {
        $this->replyToId = null;
    }

    public function removeAttachment(): void
    {
        $this->attachment?->delete();
        $this->attachment = null;
    }

    public function deleteMessage(int $messageId): void
    {
        $message = Message::query()->with('conversation')->where('conversation_id', $this->conversationId)->find($messageId);

        if (! $message) {
            return;
        }

        try {
            app(ChatService::class)->deleteMessage($this->user(), $message);
        } catch (AuthorizationException|ValidationException $e) {
            $this->fail($e);
        }
    }

    public function toggleMute(): void
    {
        if ($conversation = $this->current()) {
            $muted = app(ChatService::class)->toggleMute($this->user(), $conversation);
            Notification::make()->title($muted ? 'Percakapan di-mute' : 'Mute dimatikan')->success()->send();
        }
    }

    public function removeMember(int $userId): void
    {
        $conversation = $this->current();
        $member = User::query()->find($userId);

        if ($conversation && $member) {
            $this->run(fn () => app(ChatService::class)->removeMember($this->user(), $conversation, $member), 'Anggota dikeluarkan');
        }
    }

    // ---------------------------------------------------------------- aksi modal

    public function newChatAction(): Action
    {
        return Action::make('newChat')
            ->label('Chat baru')->icon(Heroicon::OutlinedPencilSquare)->modalWidth('md')->modalSubmitActionLabel('Mulai chat')
            ->schema([
                Select::make('user_id')->label('Dengan')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->chatUserOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->name),
            ])
            ->action(function (array $data): void {
                $other = User::query()->find((int) $data['user_id']);
                $conversation = null;
                $this->run(function () use ($other, &$conversation) {
                    $conversation = app(ChatService::class)->startPersonal($this->user(), $other ?? new User);
                }, null);
                $this->open($conversation->getKey());
            });
    }

    public function newGroupAction(): Action
    {
        return Action::make('newGroup')
            ->label('Grup baru')->icon(Heroicon::OutlinedUserGroup)->color('gray')->modalWidth('lg')->modalSubmitActionLabel('Buat grup')
            ->schema([
                TextInput::make('name')->label('Nama grup')->required()->maxLength(100),
                Select::make('members')->label('Anggota')->required()->multiple()->searchable()
                    ->helperText('Hanya akun aktif terdaftar. Pembina tidak memakai chat.')
                    ->getSearchResultsUsing(fn (string $search): array => $this->chatUserOptions($search))
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()->whereIn('id', $values)->pluck('name', 'id')->all()),
            ])
            ->action(function (array $data): void {
                $conversation = null;
                $this->run(function () use ($data, &$conversation) {
                    $conversation = app(ChatService::class)->createGroup($this->user(), $data['name'], array_map('intval', $data['members']));
                }, 'Grup dibuat');
                $this->open($conversation->getKey());
            });
    }

    public function manageGroupAction(): Action
    {
        return Action::make('manageGroup')
            ->label('Kelola grup')->icon(Heroicon::OutlinedCog6Tooth)->color('gray')->modalWidth('lg')
            ->authorize(fn (): bool => ($c = $this->current()) !== null && Gate::forUser($this->user())->allows('manage', $c))
            ->fillForm(fn (): array => ['name' => $this->current()?->name])
            ->schema([
                TextInput::make('name')->label('Nama grup')->required()->maxLength(100),
                Select::make('add')->label('Tambah anggota')->multiple()->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->chatUserOptions($search))
                    ->getOptionLabelsUsing(fn (array $values): array => User::query()->whereIn('id', $values)->pluck('name', 'id')->all()),
            ])
            ->action(function (array $data): void {
                $conversation = $this->current();
                $this->run(function () use ($conversation, $data) {
                    app(ChatService::class)->rename($this->user(), $conversation, $data['name']);

                    if (! empty($data['add'])) {
                        app(ChatService::class)->addMembers($this->user(), $conversation, array_map('intval', $data['add']));
                    }
                }, 'Grup diperbarui');
            });
    }

    public function leaveGroupAction(): Action
    {
        return Action::make('leaveGroup')
            ->label('Keluar dari grup')->icon(Heroicon::OutlinedArrowRightStartOnRectangle)->color('danger')
            ->requiresConfirmation()
            ->authorize(fn (): bool => ($c = $this->current()) !== null && Gate::forUser($this->user())->allows('leave', $c))
            ->action(function (): void {
                $this->run(fn () => app(ChatService::class)->leave($this->user(), $this->current()), 'Anda keluar dari grup');
                $this->close();
            });
    }

    public function dissolveGroupAction(): Action
    {
        return Action::make('dissolveGroup')
            ->label('Bubarkan grup')->icon(Heroicon::OutlinedTrash)->color('danger')
            ->requiresConfirmation()->modalDescription('Grup beserta seluruh pesan dan lampirannya dihapus permanen.')
            ->authorize(fn (): bool => ($c = $this->current()) !== null && Gate::forUser($this->user())->allows('dissolve', $c))
            ->action(function (): void {
                $this->run(fn () => app(ChatService::class)->dissolve($this->user(), $this->current()), 'Grup dibubarkan');
                $this->close();
            });
    }

    /** Super Admin: daftar grup buatan pengguna (metadata saja, tanpa isi pesan) untuk dibubarkan. */
    public function allGroupsAction(): Action
    {
        return Action::make('allGroups')
            ->label('Semua grup')->icon(Heroicon::OutlinedRectangleStack)->color('gray')->modalWidth('2xl')
            ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')
            ->visible(fn (): bool => $this->user()->isSuperAdmin())
            ->modalDescription('Hanya nama & jumlah anggota. Isi percakapan tidak bisa dibaca dari sini.')
            ->modalContent(fn () => view('filament.pages.partials.chat-all-groups', [
                'groups' => Conversation::query()->where('type', ConversationType::Custom->value)
                    ->withCount('members')->with('creator:id,name')->orderBy('name')->get(),
            ]));
    }

    public function dissolveAny(int $conversationId): void
    {
        $conversation = Conversation::query()->find($conversationId);

        if ($conversation) {
            $this->run(fn () => app(ChatService::class)->dissolve($this->user(), $conversation), 'Grup dibubarkan');

            if ($this->conversationId === $conversationId) {
                $this->close();
            }
        }
    }

    // ---------------------------------------------------------------- data tampilan

    protected function getViewData(): array
    {
        $user = $this->user();
        $unread = app(ChatService::class)->unreadPerConversation($user);

        $conversations = Conversation::query()->forUser($user)
            ->with(['latestMessage', 'users:id,name,photo'])
            ->orderByDesc('last_message_at')->orderBy('name')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->getKey(),
                'name' => $c->displayName($user),
                'type' => $c->type,
                'preview' => $c->latestMessage ? $c->latestMessage->preview(60) : 'Belum ada pesan',
                'time' => $c->last_message_at ? ($c->last_message_at->isToday() ? $c->last_message_at->format('H.i') : $c->last_message_at->format('d/m')) : '',
                'unread' => $unread[$c->getKey()] ?? 0,
                'initial' => mb_strtoupper(mb_substr($c->displayName($user), 0, 1)),
            ]);

        if (trim($this->search) !== '') {
            $term = mb_strtolower(trim($this->search));
            $conversations = $conversations->filter(fn (array $c) => str_contains(mb_strtolower($c['name']), $term));
        }

        return [
            'conversations' => $conversations->values(),
            'current' => $this->currentView(),
            'canSend' => ($c = $this->current()) !== null && Gate::forUser($user)->allows('send', $c),
            'replyTo' => $this->replyToId ? Message::withTrashed()->with('sender:id,name')->find($this->replyToId) : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function currentView(): ?array
    {
        $conversation = $this->current();

        if (! $conversation) {
            return null;
        }

        $user = $this->user();
        $members = $conversation->members()->with('user:id,name,is_active')->get();
        $searching = trim($this->messageSearch) !== '';

        $messages = Message::withTrashed()->with(['sender:id,name', 'replyTo.sender:id,name'])
            ->where('conversation_id', $conversation->getKey())
            ->latest('id')->limit($searching ? max($this->limit, 200) : $this->limit + 1)->get();

        $hasMore = ! $searching && $messages->count() > $this->limit;
        $messages = $messages->take($searching ? 200 : $this->limit)->reverse()->values();

        if ($searching) {
            $term = mb_strtolower(trim($this->messageSearch));
            $messages = $messages->filter(fn (Message $m) => ! $m->trashed() && str_contains(mb_strtolower((string) $m->body), $term))->values();
        }

        $others = $members->where('user_id', '!=', $user->getKey());

        return [
            'id' => $conversation->getKey(),
            'name' => $conversation->displayName($user),
            'type' => $conversation->type,
            'muted' => (bool) $members->firstWhere('user_id', $user->getKey())?->muted,
            'members' => $members->map(fn (ConversationMember $m) => [
                'id' => $m->user_id,
                'name' => $m->user?->name ?? 'Akun dihapus',
                'admin' => $m->isAdmin(),
                'inactive' => ! ($m->user?->is_active ?? false),
            ])->sortBy('name')->values(),
            'canManage' => Gate::forUser($user)->allows('manage', $conversation),
            'hasMore' => $hasMore,
            'messages' => $messages->map(fn (Message $m) => $this->presentMessage($m, $user, $others, $conversation->type)),
        ];
    }

    /**
     * @param  Collection<int, ConversationMember>  $others
     * @return array<string, mixed>
     */
    private function presentMessage(Message $m, User $user, Collection $others, ConversationType $type): array
    {
        $mine = $m->user_id === $user->getKey();
        $readBy = $mine && ! $m->trashed()
            ? $others->filter(fn (ConversationMember $o) => $o->last_read_at && $o->last_read_at->gte($m->created_at))->count()
            : 0;

        return [
            'id' => $m->getKey(),
            'mine' => $mine,
            'sender' => $m->sender?->name ?? 'Akun dihapus',
            'deleted' => $m->trashed(),
            'body' => $m->trashed() ? null : $m->body,
            'time' => $m->created_at->isToday() ? $m->created_at->format('H.i') : Format::date($m->created_at).' '.$m->created_at->format('H.i'),
            'attachment' => ! $m->trashed() && $m->hasAttachment() ? [
                'url' => route('panel.chat.attachment', $m),
                'name' => $m->attachment_name,
                'image' => $m->isImage(),
                'size' => Format::bytes($m->attachment_size),
            ] : null,
            'expired' => ! $m->trashed() && $m->attachment_expired_at !== null,
            'reply' => $m->replyTo ? ['sender' => $m->replyTo->sender?->name ?? '', 'preview' => $m->replyTo->preview(70)] : null,
            'read' => $mine && ! $m->trashed() ? ($type === ConversationType::Personal ? ($readBy > 0 ? 'Dibaca' : 'Terkirim') : ($readBy > 0 ? "Dibaca {$readBy}" : 'Terkirim')) : null,
        ];
    }

    // ---------------------------------------------------------------- bantuan

    private function current(): ?Conversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        $conversation = Conversation::query()->find($this->conversationId);

        if (! $conversation || Gate::forUser($this->user())->denies('view', $conversation)) {
            $this->conversationId = null;

            return null;
        }

        return $conversation;
    }

    private function markCurrentRead(): void
    {
        if ($conversation = $this->current()) {
            app(ChatService::class)->markRead($this->user(), $conversation);
        }
    }

    /** @return array<int, string> akun aktif yang bisa diajak chat (tanpa Pembina & diri sendiri) */
    private function chatUserOptions(string $search): array
    {
        return User::query()->where('is_active', true)->whereKeyNot($this->user()->getKey())
            ->where(fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'pembina'))
            ->with('roles', 'organization:id,name', 'division:id,name')
            ->orderBy('name')->limit(30)->get()
            ->filter(fn (User $u) => $u->canUseChat())
            ->mapWithKeys(fn (User $u) => [$u->getKey() => $u->name.' — '.($u->isApplicant() ? ($u->organization?->name ?? 'Pengaju') : ($u->division?->name ?? 'Pengurus'))])
            ->all();
    }

    private function resetComposer(): void
    {
        $this->body = '';
        $this->attachment = null;
        $this->replyToId = null;
        $this->resetErrorBag();
    }

    private function fail(AuthorizationException|ValidationException $e): void
    {
        Notification::make()->title('Tidak bisa diproses')
            ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
            ->danger()->send();
    }

    private function run(callable $callback, ?string $success): void
    {
        try {
            $callback();
        } catch (AuthorizationException|ValidationException $e) {
            $this->fail($e);

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
