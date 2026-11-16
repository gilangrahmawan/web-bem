<?php

namespace App\Services\Chat;

use App\Enums\ConversationType;
use App\Enums\DriveFileKind;
use App\Events\ChatMessageChanged;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Services\ActivityLogger;
use App\Services\ImageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chat (AGENTS.md C6 + keputusan Fase 9). Semua aksi diperiksa ConversationPolicy (berlaku juga
 * bagi Super Admin). Isi pesan terenkripsi di database; event real-time tanpa isi pesan.
 */
class ChatService
{
    public const MAX_BODY = 4000;

    public const RATE_PER_MINUTE = 30;

    public const IMAGE_MAX_KB = 2048;

    public const FILE_MAX_KB = 10240;

    public const DEFAULT_RETENTION_MONTHS = 12;

    /** Jenis berkas lampiran (selain gambar). */
    private const FILE_KINDS = [DriveFileKind::Pdf, DriveFileKind::Word, DriveFileKind::Excel, DriveFileKind::PowerPoint, DriveFileKind::Archive];

    public function __construct(
        private readonly ImageService $images,
        private readonly ActivityLogger $logger,
    ) {}

    public static function retentionMonths(): int
    {
        return max(1, (int) (Setting::get('chat_attachment_retention_months') ?? self::DEFAULT_RETENTION_MONTHS));
    }

    // ---------------------------------------------------------------- percakapan

    /** Buka (atau buat) percakapan personal 1:1 dengan akun terdaftar yang bisa memakai chat. */
    public function startPersonal(User $actor, User $other): Conversation
    {
        $this->assertCanChat($actor);

        if ($actor->is($other) || ! $other->canUseChat()) {
            throw ValidationException::withMessages(['user' => 'Akun ini tidak bisa diajak chat.']);
        }

        $key = Conversation::personalKey((int) $actor->getKey(), (int) $other->getKey());

        return DB::transaction(function () use ($actor, $other, $key): Conversation {
            $conversation = Conversation::query()->where('personal_key', $key)->lockForUpdate()->first();

            if (! $conversation) {
                $conversation = new Conversation(['type' => ConversationType::Personal->value]);
                $conversation->forceFill(['personal_key' => $key, 'created_by' => $actor->getKey()])->save();
            }

            foreach ([$actor, $other] as $user) {
                $conversation->members()->firstOrCreate(['user_id' => $user->getKey()], ['role' => ConversationMember::MEMBER]);
            }

            return $conversation;
        });
    }

    /** @param  list<int>  $memberIds */
    public function createGroup(User $actor, string $name, array $memberIds): Conversation
    {
        $this->assertCanChat($actor);
        $name = $this->cleanName($name);
        $members = $this->eligibleUsers($memberIds, $actor);

        if ($members->isEmpty()) {
            throw ValidationException::withMessages(['members' => 'Pilih minimal satu anggota grup.']);
        }

        return DB::transaction(function () use ($actor, $name, $members): Conversation {
            $conversation = new Conversation(['type' => ConversationType::Custom->value, 'name' => $name]);
            $conversation->forceFill(['created_by' => $actor->getKey()])->save();
            $conversation->members()->create(['user_id' => $actor->getKey(), 'role' => ConversationMember::ADMIN, 'last_read_at' => now()]);

            foreach ($members as $user) {
                $conversation->members()->create(['user_id' => $user->getKey(), 'role' => ConversationMember::MEMBER]);
            }

            $this->logger->log('chat.group_created', $conversation, ['name' => $name, 'members' => $members->count() + 1], $actor);

            return $conversation;
        });
    }

    /** @param  list<int>  $memberIds */
    public function addMembers(User $actor, Conversation $conversation, array $memberIds): void
    {
        $this->authorize($actor, 'manage', $conversation);
        $members = $this->eligibleUsers($memberIds, $actor);

        DB::transaction(function () use ($conversation, $members): void {
            foreach ($members as $user) {
                $conversation->members()->firstOrCreate(['user_id' => $user->getKey()], ['role' => ConversationMember::MEMBER]);
            }
        });
    }

    public function removeMember(User $actor, Conversation $conversation, User $member): void
    {
        $this->authorize($actor, 'manage', $conversation);

        if ($actor->is($member)) {
            throw ValidationException::withMessages(['member' => 'Gunakan "Keluar dari grup" untuk keluar.']);
        }

        $conversation->members()->where('user_id', $member->getKey())->delete();
    }

    public function rename(User $actor, Conversation $conversation, string $name): void
    {
        $this->authorize($actor, 'manage', $conversation);
        $conversation->forceFill(['name' => $this->cleanName($name)])->save();
    }

    /** Keluar dari grup. Admin terakhir keluar → anggota terlama jadi admin; grup kosong → dibubarkan. */
    public function leave(User $actor, Conversation $conversation): void
    {
        $this->authorize($actor, 'leave', $conversation);

        DB::transaction(function () use ($actor, $conversation): void {
            $conversation->members()->where('user_id', $actor->getKey())->delete();

            if ($conversation->members()->doesntExist()) {
                $this->destroyConversation($conversation);

                return;
            }

            if ($conversation->members()->where('role', ConversationMember::ADMIN)->doesntExist()) {
                $conversation->members()->oldest('id')->first()?->update(['role' => ConversationMember::ADMIN]);
            }
        });
    }

    /** Bubarkan grup buatan pengguna (admin grup / Super Admin). Pesan & lampiran ikut dihapus. */
    public function dissolve(User $actor, Conversation $conversation): void
    {
        $this->authorize($actor, 'dissolve', $conversation);

        DB::transaction(function () use ($actor, $conversation): void {
            $this->logger->log('chat.group_dissolved', null, ['conversation_id' => $conversation->getKey(), 'name' => $conversation->name], $actor);
            $this->destroyConversation($conversation);
        });
    }

    public function toggleMute(User $actor, Conversation $conversation): bool
    {
        $this->authorize($actor, 'view', $conversation);
        $member = $conversation->membership($actor);
        $member->update(['muted' => ! $member->muted]);

        return $member->muted;
    }

    public function markRead(User $actor, Conversation $conversation): void
    {
        if (Gate::forUser($actor)->allows('view', $conversation)) {
            $conversation->members()->where('user_id', $actor->getKey())->update(['last_read_at' => now()]);
        }
    }

    // ---------------------------------------------------------------- pesan

    public function send(User $actor, Conversation $conversation, ?string $body, ?UploadedFile $attachment = null, ?int $replyToId = null): Message
    {
        $this->authorize($actor, 'send', $conversation);

        $body = trim((string) $body);

        if ($body === '' && $attachment === null) {
            throw ValidationException::withMessages(['body' => 'Tulis pesan atau lampirkan berkas.']);
        }

        if (mb_strlen($body) > self::MAX_BODY) {
            throw ValidationException::withMessages(['body' => 'Pesan maksimal '.self::MAX_BODY.' karakter.']);
        }

        if ($replyToId !== null && Message::withTrashed()->whereKey($replyToId)->where('conversation_id', $conversation->getKey())->doesntExist()) {
            $replyToId = null;
        }

        $key = 'chat-send:'.$actor->getKey();

        if (RateLimiter::tooManyAttempts($key, self::RATE_PER_MINUTE)) {
            throw ValidationException::withMessages(['body' => 'Terlalu banyak pesan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }

        RateLimiter::hit($key, 60);

        $stored = $attachment ? $this->storeAttachment($conversation, $attachment) : null;

        try {
            $message = DB::transaction(function () use ($actor, $conversation, $body, $stored, $replyToId): Message {
                $message = new Message(['conversation_id' => $conversation->getKey(), 'body' => $body !== '' ? $body : null, 'reply_to_id' => $replyToId]);
                $message->forceFill(['user_id' => $actor->getKey(), ...($stored ?? [])])->save();

                $conversation->forceFill(['last_message_at' => $message->created_at])->save();
                $conversation->members()->where('user_id', $actor->getKey())->update(['last_read_at' => $message->created_at]);

                return $message;
            });
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk(Message::DISK)->delete($stored['attachment_path']);
            }

            throw $e;
        }

        $this->broadcast($conversation, $message, 'sent');
        $this->notifyMembers($actor, $conversation, $message);

        return $message;
    }

    /** Hapus pesan sendiri: isi & lampiran benar-benar dihapus dari server; tampil "Pesan dihapus". */
    public function deleteMessage(User $actor, Message $message): void
    {
        if (Gate::forUser($actor)->denies('deleteMessage', [$message->conversation, $message])) {
            throw new AuthorizationException('Anda hanya bisa menghapus pesan sendiri.');
        }

        DB::transaction(function () use ($message): void {
            if ($message->attachment_path) {
                Storage::disk(Message::DISK)->delete($message->attachment_path);
            }

            $message->forceFill([
                'body' => null,
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
            ])->save();
            $message->delete();
        });

        $this->broadcast($message->conversation, $message, 'deleted');
    }

    /** Jumlah pesan belum dibaca user (semua percakapan miliknya). */
    public function unreadCount(User $user): int
    {
        return (int) Message::query()
            ->join('conversation_members as cm', function ($join) use ($user): void {
                $join->on('cm.conversation_id', '=', 'messages.conversation_id')->where('cm.user_id', $user->getKey());
            })
            ->where('messages.user_id', '!=', $user->getKey())
            ->where(fn ($q) => $q->whereNull('cm.last_read_at')->orWhereColumn('messages.created_at', '>', 'cm.last_read_at'))
            ->count();
    }

    /** @return array<int, int> conversation_id => jumlah belum dibaca */
    public function unreadPerConversation(User $user): array
    {
        return Message::query()
            ->join('conversation_members as cm', function ($join) use ($user): void {
                $join->on('cm.conversation_id', '=', 'messages.conversation_id')->where('cm.user_id', $user->getKey());
            })
            ->where('messages.user_id', '!=', $user->getKey())
            ->where(fn ($q) => $q->whereNull('cm.last_read_at')->orWhereColumn('messages.created_at', '>', 'cm.last_read_at'))
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id as cid, COUNT(*) as total')
            ->pluck('total', 'cid')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Hapus lampiran yang lebih tua dari N bulan; teks pesan tetap. @return int jumlah lampiran */
    public function purgeExpiredAttachments(): int
    {
        $cutoff = now()->subMonths(self::retentionMonths());
        $count = 0;

        Message::query()->whereNotNull('attachment_path')->where('created_at', '<=', $cutoff)
            ->chunkById(100, function ($messages) use (&$count): void {
                foreach ($messages as $message) {
                    Storage::disk(Message::DISK)->delete($message->attachment_path);
                    $message->forceFill([
                        'attachment_path' => null,
                        'attachment_name' => null,
                        'attachment_size' => null,
                        'attachment_expired_at' => now(),
                    ])->saveQuietly();
                    $count++;
                }
            });

        return $count;
    }

    // ---------------------------------------------------------------- bantuan

    /** @return array{attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int} */
    private function storeAttachment(Conversation $conversation, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $directory = Message::DIRECTORY.'/'.$conversation->getKey();
        $original = Str::limit(trim($file->getClientOriginalName()) ?: 'lampiran', 200, '');

        if (in_array($extension, DriveFileKind::Image->extensions(), true) && in_array($mime, DriveFileKind::Image->mimeTypes(), true)) {
            if ($file->getSize() > self::IMAGE_MAX_KB * 1024) {
                throw ValidationException::withMessages(['attachment' => 'Gambar maksimal 2 MB.']);
            }

            $path = $this->images->storeWebp($file, $directory, 1600, Message::DISK);

            return [
                'attachment_path' => $path,
                'attachment_name' => pathinfo($original, PATHINFO_FILENAME).'.webp',
                'attachment_mime' => 'image/webp',
                'attachment_size' => (int) Storage::disk(Message::DISK)->size($path),
            ];
        }

        $kind = DriveFileKind::fromExtension($extension);

        if ($kind === null || ! in_array($kind, self::FILE_KINDS, true) || ! in_array($mime, $kind->mimeTypes(), true)) {
            throw ValidationException::withMessages(['attachment' => 'Jenis berkas tidak diizinkan. Gunakan gambar, PDF, Word, Excel, PowerPoint, atau ZIP.']);
        }

        if ($file->getSize() > self::FILE_MAX_KB * 1024) {
            throw ValidationException::withMessages(['attachment' => 'Berkas maksimal 10 MB.']);
        }

        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, Message::DISK);

        return [
            'attachment_path' => $path,
            'attachment_name' => $original,
            'attachment_mime' => $mime,
            'attachment_size' => (int) $file->getSize(),
        ];
    }

    private function broadcast(Conversation $conversation, Message $message, string $kind): void
    {
        try {
            event(new ChatMessageChanged(
                (int) $conversation->getKey(),
                (int) $message->getKey(),
                $kind,
                $conversation->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            ));
        } catch (\Throwable $e) {
            // Pusher tidak tersedia → klien tetap mendapat pesan lewat polling 5 detik.
            Log::warning('Broadcast chat gagal; klien memakai polling.', ['error' => $e->getMessage()]);
        }
    }

    private function notifyMembers(User $actor, Conversation $conversation, Message $message): void
    {
        $recipients = User::query()
            ->whereIn('id', $conversation->members()->where('user_id', '!=', $actor->getKey())->where('muted', false)->pluck('user_id'))
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->canUseChat());

        if ($recipients->isNotEmpty()) {
            try {
                Notification::send($recipients, new ChatMessageNotification($message));
            } catch (\Throwable $e) {
                Log::warning('Push chat gagal dijadwalkan.', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Anggota grup: hanya akun aktif terdaftar yang bisa memakai chat (tanpa Pembina, tanpa undangan luar).
     *
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    private function eligibleUsers(array $ids, User $actor): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $users = User::query()->whereIn('id', $ids)->where('id', '!=', $actor->getKey())->get();

        if ($users->count() !== count(array_diff($ids, [(int) $actor->getKey()])) || $users->contains(fn (User $u) => ! $u->canUseChat())) {
            throw ValidationException::withMessages(['members' => 'Anggota grup hanya boleh akun aktif terdaftar (Pembina tidak memakai chat).']);
        }

        return $users;
    }

    private function destroyConversation(Conversation $conversation): void
    {
        Storage::disk(Message::DISK)->deleteDirectory(Message::DIRECTORY.'/'.$conversation->getKey());
        Message::withTrashed()->where('conversation_id', $conversation->getKey())->update(['reply_to_id' => null]);
        $conversation->delete();
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Nama grup wajib diisi (maks. 100 karakter).']);
        }

        return $name;
    }

    private function assertCanChat(User $actor): void
    {
        if (! $actor->canUseChat()) {
            throw new AuthorizationException('Anda tidak punya akses chat.');
        }
    }

    private function authorize(User $actor, string $ability, Conversation $conversation): void
    {
        if (Gate::forUser($actor)->denies($ability, $conversation)) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }
}
