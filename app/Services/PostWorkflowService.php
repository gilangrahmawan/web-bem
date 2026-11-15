<?php

namespace App\Services;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStage;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\RoleName;
use App\Models\Post;
use App\Models\PostApproval;
use App\Models\User;
use App\Notifications\PostWorkflowNotification;
use App\Policies\PostPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Alur konten (AGENTS.md C7, D4):
 *   draft/revision ─submit→ pending_coordinator ─approveByCoordinator→ pending_leader ─approveByLeader→ approved ─publish→ published
 *   pending_* ─requestRevision→ revision (kembali ke penulis) / ─reject→ rejected
 *   published ─takeDown→ revision (turun dari website, wajib disetujui ulang)
 *
 * Keputusan pemilik: penyetuju ≠ penulis; konten buatan Koordinator Medpub melewati tahap koordinator
 * (tercatat auto_approved); Ketua/Wakil cukup satu. Bila tidak ada penyetuju aktif lain → pesan jelas.
 * Setiap pengajuan menaikkan review_round; penerbitan hanya menghitung persetujuan di putaran itu.
 */
class PostWorkflowService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function submit(User $actor, Post $post): Post
    {
        $this->authorize($actor, 'submit', $post, 'Hanya penulis yang bisa mengajukan konten draft atau revisi.');
        $this->ensureComplete($post);

        $author = $post->author;
        $authorIsCoordinator = PostPolicy::isCoordinatorApprover($author);

        if (! $authorIsCoordinator && $this->coordinators($author)->isEmpty()) {
            throw ValidationException::withMessages([
                'approver' => 'Belum ada Koordinator Medpub aktif yang dapat menyetujui konten ini. Hubungi Super Admin.',
            ]);
        }

        $this->ensureLeaderAvailable($author);

        $post = DB::transaction(function () use ($actor, $post, $authorIsCoordinator): Post {
            $from = $post->status;
            $post->forceFill([
                'review_round' => $post->review_round + 1,
                'status' => $authorIsCoordinator ? PostStatus::PendingLeader : PostStatus::PendingCoordinator,
            ])->save();

            if ($authorIsCoordinator) {
                $this->record($post, $actor, ApprovalStage::Coordinator, ApprovalDecision::AutoApproved, 'Diajukan oleh Koordinator Medpub.');
            }

            $this->log('post.submitted', $post, $actor, $from);

            return $post;
        });

        $authorIsCoordinator
            ? $this->notify($this->leaders($author), $post, 'Konten menunggu persetujuan Ketua/Wakil', $post->title)
            : $this->notify($this->coordinators($author), $post, 'Konten baru menunggu persetujuan', $post->title);

        return $post;
    }

    public function approveByCoordinator(User $actor, Post $post, ?string $note = null): Post
    {
        $this->authorize($actor, 'approveCoordinator', $post, $this->approverMessage($actor, $post));
        $this->ensureLeaderAvailable($post->author);

        $post = $this->decide($actor, $post, ApprovalStage::Coordinator, ApprovalDecision::Approved, PostStatus::PendingLeader, $note);

        $this->notify($this->leaders($post->author), $post, 'Konten menunggu persetujuan Ketua/Wakil', $post->title);

        return $post;
    }

    public function approveByLeader(User $actor, Post $post, ?string $note = null): Post
    {
        $this->authorize($actor, 'approveLeader', $post, $this->approverMessage($actor, $post));

        $post = $this->decide($actor, $post, ApprovalStage::Leader, ApprovalDecision::Approved, PostStatus::Approved, $note);

        $this->notify($this->publishers()->push($post->author)->unique('id'), $post, 'Konten disetujui, siap diterbitkan', $post->title);

        return $post;
    }

    public function requestRevision(User $actor, Post $post, string $note): Post
    {
        [$stage, $ability] = $this->currentStage($post);
        $this->authorize($actor, $ability, $post, $this->approverMessage($actor, $post));
        $note = $this->requiredNote($note, 'Tulis catatan revisi untuk penulis.');

        $post = $this->decide($actor, $post, $stage, ApprovalDecision::Revision, PostStatus::Revision, $note);

        $this->notify(collect([$post->author]), $post, 'Konten perlu direvisi', "{$post->title} — {$note}");

        return $post;
    }

    public function reject(User $actor, Post $post, string $note): Post
    {
        [$stage, $ability] = $this->currentStage($post);
        $this->authorize($actor, $ability, $post, $this->approverMessage($actor, $post));
        $note = $this->requiredNote($note, 'Tulis alasan penolakan untuk penulis.');

        $post = $this->decide($actor, $post, $stage, ApprovalDecision::Rejected, PostStatus::Rejected, $note);

        $this->notify(collect([$post->author]), $post, 'Konten ditolak', "{$post->title} — {$note}");

        return $post;
    }

    public function publish(User $actor, Post $post): Post
    {
        $this->authorize($actor, 'publish', $post, 'Hanya Koordinator Medpub yang dapat menerbitkan konten yang sudah disetujui.');

        if (! $this->hasBothApprovals($post)) {
            throw ValidationException::withMessages([
                'approval' => 'Konten belum disetujui Koordinator Medpub dan Ketua/Wakil pada pengajuan terakhir.',
            ]);
        }

        $post = DB::transaction(function () use ($actor, $post): Post {
            $from = $post->status;
            $post->forceFill([
                'status' => PostStatus::Published,
                'published_at' => $post->published_at ?? now(),
                'published_by' => $actor->getKey(),
            ])->save();

            $this->log('post.published', $post, $actor, $from);

            return $post;
        });

        $this->notify(collect([$post->author]), $post, 'Konten Anda terbit di website', $post->title);

        return $post;
    }

    public function markSocialPosted(User $actor, Post $post, string $url): Post
    {
        $this->authorize($actor, 'markSocialPosted', $post, 'Hanya Koordinator Medpub yang menandai konten sudah diposting.');

        $url = trim($url);

        if (! preg_match('#^https?://\S+$#i', $url) || mb_strlen($url) > 255) {
            throw ValidationException::withMessages(['social_post_url' => 'Link postingan harus berupa URL http/https yang valid.']);
        }

        DB::transaction(function () use ($actor, $post, $url): void {
            $post->forceFill(['social_posted_at' => now(), 'social_post_url' => $url])->save();
            $this->logger->log('post.social_posted', $post, ['url' => $url], $actor);
        });

        return $post;
    }

    /** Turunkan dari website & kembalikan ke penulis untuk revisi (alasan wajib). */
    public function takeDown(User $actor, Post $post, string $reason): Post
    {
        $this->authorize($actor, 'takeDown', $post, 'Hanya Koordinator Medpub atau Ketua/Wakil yang dapat menurunkan konten.');
        $reason = $this->requiredNote($reason, 'Alasan penurunan wajib diisi.');

        $post = $this->decide($actor, $post, ApprovalStage::Publication, ApprovalDecision::TakenDown, PostStatus::Revision, $reason, 'post.taken_down');

        $this->notify(collect([$post->author]), $post, 'Konten diturunkan untuk revisi', "{$post->title} — {$reason}");

        return $post;
    }

    /** Kedua tahap disetujui pada putaran pengajuan terakhir, oleh orang-orang selain penulis. */
    public function hasBothApprovals(Post $post): bool
    {
        $latest = fn (ApprovalStage $stage): ?PostApproval => PostApproval::query()
            ->where('post_id', $post->getKey())
            ->where('round', $post->review_round)
            ->where('stage', $stage->value)
            ->latest('id')
            ->first();

        $coordinator = $latest(ApprovalStage::Coordinator);
        $leader = $latest(ApprovalStage::Leader);

        if ($post->review_round < 1 || ! $coordinator || ! $leader) {
            return false;
        }

        $coordinatorOk = $coordinator->decision === ApprovalDecision::Approved
            || ($coordinator->decision === ApprovalDecision::AutoApproved && $coordinator->user_id === $post->author_id);

        return $coordinatorOk
            && $leader->decision === ApprovalDecision::Approved
            && $leader->user_id !== $post->author_id
            && $leader->user_id !== $coordinator->user_id
            && $leader->id > $coordinator->id;
    }

    // --- Internal --------------------------------------------------------------------------

    private function decide(
        User $actor,
        Post $post,
        ApprovalStage $stage,
        ApprovalDecision $decision,
        PostStatus $to,
        ?string $note,
        string $action = 'post.reviewed',
    ): Post {
        return DB::transaction(function () use ($actor, $post, $stage, $decision, $to, $note, $action): Post {
            $from = $post->status;
            $post->forceFill(['status' => $to])->save();
            $this->record($post, $actor, $stage, $decision, $note);
            $this->log($action, $post, $actor, $from, ['stage' => $stage->value, 'decision' => $decision->value]);

            return $post;
        });
    }

    private function record(Post $post, User $actor, ApprovalStage $stage, ApprovalDecision $decision, ?string $note): void
    {
        $post->approvals()->create([
            'user_id' => $actor->getKey(),
            'round' => $post->review_round,
            'stage' => $stage,
            'decision' => $decision,
            'note' => filled($note) ? trim($note) : null,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function log(string $action, Post $post, User $actor, PostStatus $from, array $extra = []): void
    {
        $this->logger->log($action, $post, [
            'title' => $post->title,
            'from' => $from->value,
            'to' => $post->status->value,
            'round' => $post->review_round,
            ...$extra,
        ], $actor);
    }

    /** @return array{0: ApprovalStage, 1: string} */
    private function currentStage(Post $post): array
    {
        return match ($post->status) {
            PostStatus::PendingCoordinator => [ApprovalStage::Coordinator, 'approveCoordinator'],
            PostStatus::PendingLeader => [ApprovalStage::Leader, 'approveLeader'],
            default => throw ValidationException::withMessages([
                'status' => "Konten berstatus {$post->status->label()} tidak sedang menunggu persetujuan.",
            ]),
        };
    }

    private function ensureComplete(Post $post): void
    {
        $missing = match ($post->type) {
            PostType::Social => array_filter([
                blank($post->cover) ? 'gambar cover' : null,
                blank($post->social_caption) ? 'caption medsos' : null,
            ]),
            default => blank(strip_tags((string) $post->body)) && blank($post->attachment_path) ? ['isi atau lampiran PDF'] : [],
        };

        if ($missing !== []) {
            throw ValidationException::withMessages(['body' => 'Lengkapi dulu sebelum diajukan: '.implode(', ', $missing).'.']);
        }
    }

    private function ensureLeaderAvailable(User $author): void
    {
        if ($this->leaders($author)->isEmpty()) {
            throw ValidationException::withMessages([
                'approver' => 'Tidak ada Ketua/Wakil aktif lain yang dapat menyetujui konten ini (penulis tidak boleh menyetujui kontennya sendiri).',
            ]);
        }
    }

    private function approverMessage(User $actor, Post $post): string
    {
        return $post->author_id === $actor->getKey()
            ? 'Anda tidak boleh menyetujui konten Anda sendiri; konten ini harus disetujui pengurus lain.'
            : 'Anda tidak berhak memproses konten pada tahap ini.';
    }

    private function authorize(User $actor, string $ability, Post $post, string $message): void
    {
        if (Gate::forUser($actor)->denies($ability, $post)) {
            throw new AuthorizationException($message);
        }
    }

    private function requiredNote(string $note, string $message): string
    {
        $note = trim($note);

        if (mb_strlen($note) < 5) {
            throw ValidationException::withMessages(['note' => $message]);
        }

        return $note;
    }

    /** @return Collection<int, User> Koordinator Medpub aktif (bukan super_admin), selain penulis. */
    private function coordinators(User $author): Collection
    {
        return User::query()->active()->permission('post.approve_coordinator')
            ->withoutRole(RoleName::SuperAdmin->value)->whereKeyNot($author->getKey())->get();
    }

    /** @return Collection<int, User> Ketua/Wakil aktif selain penulis. */
    private function leaders(User $author): Collection
    {
        return User::query()->active()->role(RoleName::SuperAdmin->value)->whereKeyNot($author->getKey())->get();
    }

    /** @return Collection<int, User> */
    private function publishers(): Collection
    {
        return User::query()->active()->permission('post.publish')->withoutRole(RoleName::SuperAdmin->value)->get();
    }

    private function notify(Collection $users, Post $post, string $title, string $body): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, new PostWorkflowNotification($post, $title, $body));
        }
    }
}
