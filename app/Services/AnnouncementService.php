<?php

namespace App\Services;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\AnnouncementTarget;
use App\Models\User;
use App\Notifications\AnnouncementReceivedNotification;
use App\Services\Letters\LetterArchiver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Kirim pengumuman/surat & aksi penerima (AGENTS.md Fase 5).
 * Target (semua / role / divisi / user) diubah menjadi daftar pengurus AKTIF yang punya role
 * (termasuk Pembina bila tercakup); user nonaktif & pengirim sendiri tidak menerima.
 */
class AnnouncementService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly LetterArchiver $archiver,
    ) {}

    /**
     * @param  array{title: string, body: string, target_all?: bool, roles?: list<string>, divisions?: list<int|string>,
     *     users?: list<int|string>, attachment_path?: ?string, attachment_name?: ?string}  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function send(User $actor, AnnouncementType $type, array $data): Announcement
    {
        if (Gate::forUser($actor)->denies('createType', [Announcement::class, $type])) {
            throw new AuthorizationException("Anda tidak berhak mengirim {$type->label()}.");
        }

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($title === '' || $body === '') {
            throw ValidationException::withMessages(['title' => 'Judul dan isi wajib diisi.']);
        }

        $targetAll = (bool) ($data['target_all'] ?? false);
        $roles = $targetAll ? [] : array_values(array_filter((array) ($data['roles'] ?? []), 'filled'));
        $divisions = $targetAll ? [] : array_map('intval', array_filter((array) ($data['divisions'] ?? []), 'filled'));
        $users = $targetAll ? [] : array_map('intval', array_filter((array) ($data['users'] ?? []), 'filled'));

        if (! $targetAll && $roles === [] && $divisions === [] && $users === []) {
            throw ValidationException::withMessages(['target_all' => 'Pilih minimal satu target penerima.']);
        }

        $recipients = $this->resolveRecipients($actor, $targetAll, $roles, $divisions, $users);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['target_all' => 'Target yang dipilih tidak memiliki penerima aktif.']);
        }

        $announcement = DB::transaction(function () use ($actor, $type, $title, $body, $targetAll, $roles, $divisions, $users, $recipients, $data): Announcement {
            $announcement = Announcement::query()->create([
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'attachment_path' => $data['attachment_path'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'target_all' => $targetAll,
                'created_by' => $actor->getKey(),
            ]);

            $roleIds = $roles === [] ? [] : Role::query()->whereIn('name', $roles)->pluck('id')->all();
            $targets = [
                ...array_map(fn ($id) => ['target_type' => AnnouncementTarget::ROLE, 'target_id' => $id], $roleIds),
                ...array_map(fn ($id) => ['target_type' => AnnouncementTarget::DIVISION, 'target_id' => $id], $divisions),
                ...array_map(fn ($id) => ['target_type' => AnnouncementTarget::USER, 'target_id' => $id], $users),
            ];

            foreach ($targets as $target) {
                $announcement->targets()->create($target);
            }

            $now = now();
            AnnouncementRecipient::query()->insert($recipients->map(fn (User $user) => [
                'announcement_id' => $announcement->getKey(),
                'user_id' => $user->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            $this->logger->log("{$type->value}.sent", $announcement, [
                'title' => $title,
                'target_all' => $targetAll,
                'recipients' => $recipients->count(),
            ], $actor);

            return $announcement;
        });

        Notification::send($recipients, new AnnouncementReceivedNotification($announcement));

        if ($type === AnnouncementType::Letter) {
            $this->archiver->archive($announcement);
        }

        return $announcement;
    }

    /** Ditandai dibaca otomatis saat dibuka di Kotak Masuk. */
    public function markRead(User $user, AnnouncementRecipient $recipient): void
    {
        $this->ensureOwner($user, $recipient);

        if ($recipient->read_at === null) {
            $recipient->update(['read_at' => now()]);
        }
    }

    public function markFollowedUp(User $user, AnnouncementRecipient $recipient): void
    {
        $this->ensureOwner($user, $recipient);

        DB::transaction(function () use ($user, $recipient): void {
            $recipient->update(['followed_up_at' => now(), 'read_at' => $recipient->read_at ?? now()]);
            $this->logger->log('letter.followed_up', $recipient->announcement, ['recipient_id' => $recipient->getKey()], $user);
        });
    }

    /** Balasan penerima (khusus surat). */
    public function reply(User $user, AnnouncementRecipient $recipient, string $reply): void
    {
        $this->ensureOwner($user, $recipient);

        if ($recipient->announcement->type !== AnnouncementType::Letter) {
            throw ValidationException::withMessages(['reply' => 'Pengumuman tidak bisa dibalas.']);
        }

        $reply = trim($reply);

        if ($reply === '' || mb_strlen($reply) > 2000) {
            throw ValidationException::withMessages(['reply' => 'Balasan wajib diisi, maksimal 2.000 karakter.']);
        }

        DB::transaction(function () use ($user, $recipient, $reply): void {
            $recipient->update(['reply' => $reply, 'replied_at' => now(), 'read_at' => $recipient->read_at ?? now()]);
            $this->logger->log('letter.replied', $recipient->announcement, ['recipient_id' => $recipient->getKey()], $user);
        });
    }

    /**
     * @param  list<string>  $roles
     * @param  list<int>  $divisions
     * @param  list<int>  $users
     * @return Collection<int, User>
     */
    public function resolveRecipients(User $actor, bool $targetAll, array $roles, array $divisions, array $users): Collection
    {
        return User::query()
            ->active()
            ->whereHas('roles')
            ->whereKeyNot($actor->getKey())
            ->when(! $targetAll, fn (Builder $q) => $q->where(function (Builder $q) use ($roles, $divisions, $users): void {
                $q->whereRaw('1 = 0')
                    ->when($roles !== [], fn (Builder $q) => $q->orWhereHas('roles', fn (Builder $r) => $r->whereIn('name', $roles)))
                    ->when($divisions !== [], fn (Builder $q) => $q->orWhereIn('division_id', $divisions))
                    ->when($users !== [], fn (Builder $q) => $q->orWhereIn('users.id', $users));
            }))
            ->get();
    }

    private function ensureOwner(User $user, AnnouncementRecipient $recipient): void
    {
        if ($recipient->user_id !== $user->getKey()) {
            throw new AuthorizationException('Pesan ini bukan milik Anda.');
        }
    }
}
