<?php

namespace App\Services;

use App\Enums\AspirationStatus;
use App\Enums\DiscussionStatus;
use App\Enums\RoleName;
use App\Models\Aspiration;
use App\Models\Discussion;
use App\Models\User;
use App\Notifications\AspirationActivityNotification;
use App\Support\TrackingCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Alur aspirasi (AGENTS.md C7). Setiap perpindahan status di dalam transaction, tercatat di
 * aspiration_logs (catatan internal) dan activity_logs, lalu mengirim notifikasi.
 *
 *   new ─review→ in_review ─resolve→ resolved
 *                in_review ─escalate→ escalated ─forwardToKajian→ in_study ─submitStudy→ returned ─resolve→ resolved
 *   new/in_review (Humas) atau escalated (Ketua/Wakil) ─reject→ rejected (alasan tampil ke pengirim)
 */
class AspirationService
{
    public const MIN_REASON = 15;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Kiriman dari form publik (data sudah divalidasi Form Request).
     * Anonim: nama & kontak dipaksa kosong. Tidak ada IP yang disimpan.
     *
     * @param  array{name?: ?string, contact?: ?string, is_anonymous?: bool, category: string, body: string}  $data
     */
    public function submit(array $data): Aspiration
    {
        $anonymous = (bool) ($data['is_anonymous'] ?? false);

        $aspiration = DB::transaction(function () use ($data, $anonymous): Aspiration {
            $aspiration = Aspiration::query()->create([
                'tracking_code' => TrackingCode::generate('ASP', fn (string $code) => Aspiration::query()->where('tracking_code', $code)->exists()),
                'name' => $anonymous ? null : (filled($data['name'] ?? null) ? trim($data['name']) : null),
                'contact' => $anonymous ? null : (filled($data['contact'] ?? null) ? trim($data['contact']) : null),
                'is_anonymous' => $anonymous,
                'category' => $data['category'],
                'body' => trim($data['body']),
                'status' => AspirationStatus::New,
            ]);

            $aspiration->logs()->create(['from_status' => null, 'to_status' => AspirationStatus::New]);

            $this->logger->log('aspiration.submitted', $aspiration, [
                'tracking_code' => $aspiration->tracking_code,
                'category' => $aspiration->category->value,
                'anonymous' => $anonymous,
            ], recordIp: false);

            return $aspiration;
        });

        $this->notify($this->staffWith('aspiration.handle'), $aspiration, 'Aspirasi baru masuk', "{$aspiration->tracking_code} · {$aspiration->category->label()}");

        return $aspiration;
    }

    /** Humas mulai meninjau. */
    public function review(User $actor, Aspiration $aspiration, ?string $note = null): Aspiration
    {
        $this->authorize($actor, 'handle', $aspiration);

        return $this->transition($actor, $aspiration, [AspirationStatus::New], AspirationStatus::InReview, $note, ['handled_by' => $actor->getKey()]);
    }

    /** Simpan/ubah tanggapan untuk pengirim (tampil di cek status setelah selesai). */
    public function respond(User $actor, Aspiration $aspiration, string $response): Aspiration
    {
        $this->authorize($actor, 'handle', $aspiration);
        $this->ensureStatus($aspiration, [AspirationStatus::InReview, AspirationStatus::Returned]);
        $response = $this->required($response, 'response', 'Tanggapan wajib diisi.');

        DB::transaction(function () use ($actor, $aspiration, $response): void {
            $aspiration->update(['response' => $response, 'handled_by' => $aspiration->handled_by ?? $actor->getKey()]);
            $this->logger->log('aspiration.responded', $aspiration, ['tracking_code' => $aspiration->tracking_code], $actor);
        });

        return $aspiration;
    }

    /** Selesaikan dengan tanggapan untuk pengirim. */
    public function resolve(User $actor, Aspiration $aspiration, ?string $response = null, ?string $note = null): Aspiration
    {
        $this->authorize($actor, 'handle', $aspiration);
        $this->ensureStatus($aspiration, [AspirationStatus::InReview, AspirationStatus::Returned]);

        $response = filled($response) ? trim($response) : $aspiration->response;
        $response = $this->required((string) $response, 'response', 'Isi tanggapan untuk pengirim sebelum menyelesaikan aspirasi.');

        return $this->transition($actor, $aspiration, [AspirationStatus::InReview, AspirationStatus::Returned], AspirationStatus::Resolved, $note, [
            'response' => $response,
        ]);
    }

    /** Koordinator Humas menaikkan ke Ketua/Wakil. */
    public function escalate(User $actor, Aspiration $aspiration, string $note): Aspiration
    {
        $this->authorize($actor, 'escalate', $aspiration);
        $note = $this->required($note, 'note', 'Tulis alasan eskalasi untuk Ketua/Wakil.');

        $aspiration = $this->transition($actor, $aspiration, [AspirationStatus::InReview], AspirationStatus::Escalated, $note);

        $this->notify(
            User::query()->active()->role(RoleName::SuperAdmin->value)->whereKeyNot($actor->getKey())->get(),
            $aspiration,
            'Aspirasi dieskalasi',
            "{$aspiration->tracking_code} perlu keputusan Ketua/Wakil.",
        );

        return $aspiration;
    }

    /** Ketua/Wakil meneruskan ke Divisi Kajian. */
    public function forwardToKajian(User $actor, Aspiration $aspiration, ?string $note = null): Aspiration
    {
        $this->authorize($actor, 'forward', $aspiration);

        $aspiration = $this->transition($actor, $aspiration, [AspirationStatus::Escalated], AspirationStatus::InStudy, $note);

        $this->notify($this->staffWith('aspiration.study'), $aspiration, 'Aspirasi perlu dikaji', "{$aspiration->tracking_code} diteruskan ke Divisi Kajian.");

        return $aspiration;
    }

    /**
     * Kajian mengirim hasil kajian → kembali ke Humas. Membuat entri Pembahasan baru yang tertaut,
     * atau menautkan pembahasan yang sudah ada.
     */
    public function submitStudy(User $actor, Aspiration $aspiration, string $study, ?int $discussionId = null): Aspiration
    {
        $this->authorize($actor, 'study', $aspiration);
        $this->ensureStatus($aspiration, [AspirationStatus::InStudy]);
        $study = $this->required($study, 'study', 'Hasil kajian wajib diisi.');

        $aspiration = DB::transaction(function () use ($actor, $aspiration, $study, $discussionId): Aspiration {
            if ($discussionId !== null) {
                $discussion = Discussion::query()->findOrFail($discussionId);
                $discussion->update(['aspiration_id' => $aspiration->getKey()]);
            } else {
                $discussion = Discussion::query()->create([
                    'title' => "Kajian aspirasi {$aspiration->tracking_code}",
                    'body' => nl2br(e($study)),
                    'status' => DiscussionStatus::Discussed,
                    'aspiration_id' => $aspiration->getKey(),
                    'created_by' => $actor->getKey(),
                ]);
            }

            return $this->transition($actor, $aspiration, [AspirationStatus::InStudy], AspirationStatus::Returned, "Hasil kajian: {$study}", [], [
                'discussion_id' => $discussion->getKey(),
            ]);
        });

        $this->notify($this->staffWith('aspiration.handle'), $aspiration, 'Hasil kajian aspirasi', "{$aspiration->tracking_code} kembali ke Humas untuk ditanggapi.");

        return $aspiration;
    }

    /**
     * Tolak aspirasi. Alasan WAJIB dan ditampilkan ke pengirim di cek status;
     * catatan internal (opsional) tidak pernah tampil ke publik.
     */
    public function reject(User $actor, Aspiration $aspiration, string $reason, ?string $note = null): Aspiration
    {
        $allowed = match ($aspiration->status) {
            AspirationStatus::New, AspirationStatus::InReview => Gate::forUser($actor)->allows('handle', $aspiration),
            AspirationStatus::Escalated => Gate::forUser($actor)->allows('forward', $aspiration),
            default => null,
        };

        if ($allowed === null) {
            throw ValidationException::withMessages(['status' => "Aspirasi berstatus {$aspiration->status->label()} tidak bisa ditolak."]);
        }

        if (! $allowed || ! $actor->is_active) {
            throw new AuthorizationException('Anda tidak berhak menolak aspirasi ini.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Alasan penolakan untuk pengirim wajib diisi (minimal '.self::MIN_REASON.' karakter).',
            ]);
        }

        return $this->transition($actor, $aspiration, [AspirationStatus::New, AspirationStatus::InReview, AspirationStatus::Escalated], AspirationStatus::Rejected, $note, [
            'rejection_reason' => $reason,
            'handled_by' => $aspiration->handled_by ?? $actor->getKey(),
        ]);
    }

    /**
     * @param  list<AspirationStatus>  $from
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extraLog
     */
    private function transition(
        User $actor,
        Aspiration $aspiration,
        array $from,
        AspirationStatus $to,
        ?string $note,
        array $attributes = [],
        array $extraLog = [],
    ): Aspiration {
        $this->ensureStatus($aspiration, $from);

        return DB::transaction(function () use ($actor, $aspiration, $to, $note, $attributes, $extraLog): Aspiration {
            $previous = $aspiration->status;

            $aspiration->update([...$attributes, 'status' => $to]);

            $aspiration->logs()->create([
                'from_status' => $previous,
                'to_status' => $to,
                'note' => filled($note) ? trim($note) : null,
                'user_id' => $actor->getKey(),
            ]);

            $this->logger->log('aspiration.status_changed', $aspiration, [
                'tracking_code' => $aspiration->tracking_code,
                'from' => $previous->value,
                'to' => $to->value,
                ...$extraLog,
            ], $actor);

            return $aspiration;
        });
    }

    /** @param  list<AspirationStatus>  $allowed */
    private function ensureStatus(Aspiration $aspiration, array $allowed): void
    {
        if (! in_array($aspiration->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Aksi ini tidak bisa dilakukan saat aspirasi berstatus {$aspiration->status->label()}.",
            ]);
        }
    }

    private function authorize(User $actor, string $ability, Aspiration $aspiration): void
    {
        if (! $actor->is_active || Gate::forUser($actor)->denies($ability, $aspiration)) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }

    private function required(string $value, string $field, string $message): string
    {
        $value = trim($value);

        if ($value === '') {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $value;
    }

    /** Pengurus aktif pemegang permission, tanpa super_admin (agar lonceng Ketua/Wakil tidak penuh). */
    private function staffWith(string $permission): Collection
    {
        return User::query()->active()->permission($permission)->withoutRole(RoleName::SuperAdmin->value)->get();
    }

    private function notify(Collection $users, Aspiration $aspiration, string $title, string $body): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, new AspirationActivityNotification($aspiration, $title, $body));
        }
    }
}
