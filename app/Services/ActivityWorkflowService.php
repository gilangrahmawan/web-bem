<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\ActivityPicTransfer;
use App\Models\Division;
use App\Models\Event;
use App\Models\Organization;
use App\Models\StudentActivity;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\Activities\LpjSanctionPolicy;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Alur kegiatan (keputusan Fase 7, AGENTS.md C7):
 *   draft ─submitProposal→ proposal_submitted ─(Sekretaris)→ proposal_verified ─(Bendahara)→ proposal_budget_reviewed
 *         ─(Ketua/Wakil: setuju)→ approved [agenda kalender publik dibuat] ─submitLpj→ lpj_submitted → … → completed
 *   Revisi di tiap tahap (Sekretaris/Bendahara/Ketua) → *_revision → diajukan ulang dari Sekretaris.
 *   Proposal bisa ditolak (hanya Ketua/Wakil); LPJ tidak bisa ditolak. Batal → cancelled (agenda dihapus).
 * Penyetuju ≠ penanggung jawab/pembuat; super_admin cadangan tahap Sekretaris/Bendahara.
 */
class ActivityWorkflowService
{
    public const DECISION_APPROVE = 'approve';

    public const DECISION_REVISION = 'revision';

    public const DECISION_REJECT = 'reject';

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly EventConflictService $conflicts,
        private readonly LpjSanctionPolicy $sanctions,
    ) {}

    // --- Draft -----------------------------------------------------------------------------

    /** @param  array<string, mixed>  $data */
    public function saveDraft(User $actor, array $data, ?StudentActivity $activity = null): StudentActivity
    {
        if ($activity === null ? Gate::forUser($actor)->denies('create', StudentActivity::class) : Gate::forUser($actor)->denies('update', $activity)) {
            throw new AuthorizationException($activity ? 'Kegiatan hanya bisa diubah penanggung jawab saat draft atau revisi.' : 'Anda tidak berhak mengajukan kegiatan.');
        }

        $attributes = $this->validatedDraft($actor, $data);

        return DB::transaction(function () use ($actor, $attributes, $activity): StudentActivity {
            if ($activity === null) {
                $activity = new StudentActivity($attributes);
                $activity->forceFill([
                    'pic_user_id' => $actor->getKey(),
                    'created_by' => $actor->getKey(),
                    'status' => ActivityStatus::Draft,
                ])->save();

                $this->log($activity, $actor, 'system', null, ActivityStatus::Draft, 'Kegiatan dibuat.');
                $this->logger->log('activity.created', $activity, ['name' => $activity->name], $actor);

                return $activity;
            }

            $oldProposal = $activity->proposal_path;
            $activity->fill($attributes)->save();

            if ($oldProposal && $oldProposal !== $activity->proposal_path) {
                Storage::disk(StudentActivity::DISK)->delete($oldProposal);
            }

            $this->logger->log('activity.updated', $activity, ['changed' => array_keys($activity->getChanges())], $actor);

            return $activity;
        });
    }

    // --- Proposal --------------------------------------------------------------------------

    /** Agenda UKM/BEM yang bentrok dengan tanggal kegiatan (peringatan, boleh tetap diajukan). */
    public function conflictsFor(StudentActivity $activity): Collection
    {
        return $this->conflicts->findConflicts($this->calendarCategory($activity), CarbonImmutable::parse($activity->starts_on), CarbonImmutable::parse($activity->ends_on), $activity->event_id);
    }

    public function submitProposal(User $actor, StudentActivity $activity): StudentActivity
    {
        $this->authorize($actor, 'submitProposal', $activity, 'Hanya penanggung jawab yang bisa mengajukan proposal (saat draft/revisi).');

        if (blank($activity->proposal_path) || ! Storage::disk(StudentActivity::DISK)->exists((string) $activity->proposal_path)) {
            throw ValidationException::withMessages(['proposal_path' => 'Unggah file proposal (PDF) terlebih dahulu.']);
        }

        if ($message = $this->sanctions->blocksNewProposal($activity->organization, $activity)) {
            throw ValidationException::withMessages(['sanction' => $message]);
        }

        $this->ensureReviewersAvailable($activity);

        $activity = $this->transition($actor, $activity, ActivityStatus::ProposalSubmitted, 'proposal', 'Proposal diajukan.', [
            'review_round' => $activity->review_round + 1,
            'proposal_submitted_at' => now(),
        ]);

        $this->notifyStep($activity, 'Proposal kegiatan baru menunggu verifikasi');

        return $activity;
    }

    // --- LPJ -------------------------------------------------------------------------------

    public function submitLpj(User $actor, StudentActivity $activity, ?string $lpjPath, ?string $lpjName, mixed $budgetRealization): StudentActivity
    {
        $this->authorize($actor, 'submitLpj', $activity, 'LPJ hanya bisa diunggah penanggung jawab setelah proposal disetujui.');

        $lpjPath = filled($lpjPath) ? (string) $lpjPath : $activity->lpj_path;

        if (blank($lpjPath) || ! str_starts_with($lpjPath, StudentActivity::DIRECTORY.'/') || ! Storage::disk(StudentActivity::DISK)->exists($lpjPath)) {
            throw ValidationException::withMessages(['lpj_path' => 'Unggah file LPJ (PDF) terlebih dahulu.']);
        }

        if (! is_numeric($budgetRealization) || (int) $budgetRealization < 0) {
            throw ValidationException::withMessages(['budget_realization' => 'Isi realisasi anggaran (angka, minimal 0).']);
        }

        $this->ensureReviewersAvailable($activity);
        $old = $activity->lpj_path;

        $activity = $this->transition($actor, $activity, ActivityStatus::LpjSubmitted, 'lpj', 'LPJ diunggah.', [
            'lpj_path' => $lpjPath,
            'lpj_name' => $lpjName ?: $activity->lpj_name,
            'budget_realization' => (int) $budgetRealization,
            'lpj_submitted_at' => now(),
            'review_round' => $activity->review_round + 1,
        ]);

        if ($old && $old !== $lpjPath) {
            Storage::disk(StudentActivity::DISK)->delete($old);
        }

        $this->notifyStep($activity, 'LPJ kegiatan menunggu verifikasi');

        return $activity;
    }

    // --- Review ----------------------------------------------------------------------------

    /**
     * Keputusan reviewer pada tahap saat ini: approve | revision | reject (reject hanya Ketua/Wakil & proposal).
     */
    public function review(User $actor, StudentActivity $activity, string $decision, ?string $note = null): StudentActivity
    {
        $step = $activity->status->reviewStep();

        if ($step === null) {
            throw ValidationException::withMessages(['status' => "Kegiatan berstatus {$activity->status->label()} tidak sedang direview."]);
        }

        $this->authorize($actor, 'review', $activity, $this->reviewDeniedMessage($actor, $activity));

        $lpj = $activity->status->isLpjStage();
        $stage = $lpj ? 'lpj' : 'proposal';
        $note = filled($note) ? trim((string) $note) : null;

        if ($decision !== self::DECISION_APPROVE && mb_strlen((string) $note) < 5) {
            throw ValidationException::withMessages(['note' => 'Catatan wajib diisi saat meminta revisi atau menolak.']);
        }

        if ($decision === self::DECISION_REJECT && ($step !== 'leader' || $lpj)) {
            throw ValidationException::withMessages(['decision' => $lpj
                ? 'LPJ tidak bisa ditolak; minta revisi bila perlu perbaikan.'
                : 'Hanya Ketua/Wakil yang dapat menolak proposal.']);
        }

        return match ($decision) {
            self::DECISION_APPROVE => $this->approveStep($actor, $activity, $step, $lpj, $note),
            self::DECISION_REVISION => $this->returnForRevision($actor, $activity, $stage, $lpj, $note),
            self::DECISION_REJECT => $this->rejectProposal($actor, $activity, $note),
            default => throw ValidationException::withMessages(['decision' => 'Keputusan tidak dikenal.']),
        };
    }

    private function approveStep(User $actor, StudentActivity $activity, string $step, bool $lpj, ?string $note): StudentActivity
    {
        $stage = $lpj ? 'lpj' : 'proposal';

        if ($step === 'secretary') {
            $activity = $this->transition($actor, $activity, $lpj ? ActivityStatus::LpjVerified : ActivityStatus::ProposalVerified, $stage, $note ?? 'Kelengkapan diverifikasi Sekretaris.');
            $this->notifyStep($activity, ($lpj ? 'LPJ' : 'Proposal').' menunggu review Bendahara');

            return $activity;
        }

        if ($step === 'treasurer') {
            $activity = $this->transition($actor, $activity, $lpj ? ActivityStatus::LpjBudgetReviewed : ActivityStatus::ProposalBudgetReviewed, $stage, $note ?? 'Anggaran direview Bendahara.');
            $this->notifyStep($activity, ($lpj ? 'LPJ' : 'Proposal').' menunggu keputusan Ketua/Wakil');

            return $activity;
        }

        if ($lpj) {
            $activity = $this->transition($actor, $activity, ActivityStatus::Completed, 'lpj', $note ?? 'LPJ disetujui. Kegiatan selesai.', ['completed_at' => now()]);
            $this->notifyPic($activity, 'LPJ disetujui — kegiatan selesai', $activity->name);

            return $activity;
        }

        $activity = DB::transaction(function () use ($actor, $activity, $note): StudentActivity {
            $activity = $this->transition($actor, $activity, ActivityStatus::Approved, 'proposal', $note ?? 'Proposal disetujui.', ['proposal_approved_at' => now()]);
            $this->publishToCalendar($activity);

            return $activity;
        });

        $this->notifyPic($activity, 'Proposal disetujui', "{$activity->name}. Unggah LPJ paling lambat ".Format::date($activity->lpjDueOn()).'.');

        return $activity;
    }

    private function returnForRevision(User $actor, StudentActivity $activity, string $stage, bool $lpj, string $note): StudentActivity
    {
        $activity = $this->transition($actor, $activity, $lpj ? ActivityStatus::LpjRevision : ActivityStatus::ProposalRevision, $stage, $note);
        $this->notifyPic($activity, ($lpj ? 'LPJ' : 'Proposal').' perlu revisi', "{$activity->name} — {$note}");

        return $activity;
    }

    private function rejectProposal(User $actor, StudentActivity $activity, string $note): StudentActivity
    {
        $activity = DB::transaction(function () use ($actor, $activity, $note): StudentActivity {
            $activity = $this->transition($actor, $activity, ActivityStatus::Rejected, 'proposal', $note);
            $this->removeFromCalendar($activity);

            return $activity;
        });

        $this->notifyPic($activity, 'Proposal ditolak', "{$activity->name} — {$note}");

        return $activity;
    }

    // --- Batal -----------------------------------------------------------------------------

    public function cancel(User $actor, StudentActivity $activity, string $reason): StudentActivity
    {
        $this->authorize($actor, 'cancel', $activity, 'Hanya penanggung jawab atau Ketua/Wakil yang dapat membatalkan kegiatan.');

        if ($activity->status->isFinal()) {
            throw ValidationException::withMessages(['status' => 'Kegiatan ini sudah selesai/ditutup.']);
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        $activity = DB::transaction(function () use ($actor, $activity, $reason): StudentActivity {
            $activity = $this->transition($actor, $activity, ActivityStatus::Cancelled, 'system', "Dibatalkan: {$reason}");
            $this->removeFromCalendar($activity);

            return $activity;
        });

        if ($actor->getKey() !== $activity->pic_user_id) {
            $this->notifyPic($activity, 'Kegiatan dibatalkan', "{$activity->name} — {$reason}");
        }

        return $activity;
    }

    // --- Penanggung jawab ------------------------------------------------------------------

    /** Akun yang boleh menjadi penanggung jawab: aktif & dari organisasi yang sama. */
    public function eligiblePics(StudentActivity $activity): Collection
    {
        $query = User::query()->active()->whereKeyNot($activity->pic_user_id);

        return $activity->organization->isBem()
            // Kegiatan BEM: pengurus BEM aktif (bukan pengaju & bukan pembina).
            ? $query->whereHas('roles')->withoutRole([RoleName::PengajuKegiatan->value, RoleName::Pembina->value])->orderBy('name')->get()
            : $query->where('organization_id', $activity->organization_id)->role(RoleName::PengajuKegiatan->value)->orderBy('name')->get();
    }

    public function requestPicTransfer(User $actor, StudentActivity $activity, int $toUserId, ?string $note = null): ActivityPicTransfer
    {
        $this->authorize($actor, 'requestPicTransfer', $activity, 'Hanya penanggung jawab atau Sekretaris yang dapat mengajukan pindah penanggung jawab.');

        $target = $this->eligiblePics($activity)->firstWhere('id', $toUserId);

        if ($target === null) {
            throw ValidationException::withMessages(['to_user_id' => 'Penanggung jawab baru harus akun aktif dari organisasi yang sama.']);
        }

        if ($activity->picTransfers()->where('status', ActivityPicTransfer::PENDING)->exists()) {
            throw ValidationException::withMessages(['to_user_id' => 'Masih ada permintaan pindah penanggung jawab yang belum diputuskan.']);
        }

        $transfer = DB::transaction(function () use ($actor, $activity, $target, $note): ActivityPicTransfer {
            $transfer = $activity->picTransfers()->create([
                'from_user_id' => $activity->pic_user_id,
                'to_user_id' => $target->getKey(),
                'requested_by' => $actor->getKey(),
                'status' => ActivityPicTransfer::PENDING,
                'note' => filled($note) ? trim($note) : null,
            ]);

            $this->log($activity, $actor, 'pic', null, null, "Pengajuan pindah penanggung jawab ke {$target->name}.");
            $this->logger->log('activity.pic_transfer_requested', $activity, ['to_user_id' => $target->getKey()], $actor);

            return $transfer;
        });

        $this->notify($this->usersWith('submission.verify'), $activity, 'Permintaan pindah penanggung jawab', "{$activity->name} → {$target->name}");

        return $transfer;
    }

    public function decidePicTransfer(User $actor, ActivityPicTransfer $transfer, bool $approve, ?string $note = null): ActivityPicTransfer
    {
        $activity = $transfer->activity;
        $this->authorize($actor, 'decidePicTransfer', $activity, 'Hanya Sekretaris yang dapat memutuskan pindah penanggung jawab.');

        if ($transfer->status !== ActivityPicTransfer::PENDING) {
            throw ValidationException::withMessages(['transfer' => 'Permintaan ini sudah diputuskan.']);
        }

        if ($approve && ! $this->eligiblePics($activity)->contains('id', $transfer->to_user_id)) {
            throw ValidationException::withMessages(['transfer' => 'Akun tujuan sudah tidak aktif atau tidak lagi di organisasi yang sama.']);
        }

        DB::transaction(function () use ($actor, $transfer, $activity, $approve, $note): void {
            $transfer->update([
                'status' => $approve ? ActivityPicTransfer::APPROVED : ActivityPicTransfer::REJECTED,
                'decided_by' => $actor->getKey(),
                'decided_at' => now(),
                'note' => trim(($transfer->note ? $transfer->note."\n" : '').($note ?? '')) ?: null,
            ]);

            if ($approve) {
                $activity->forceFill(['pic_user_id' => $transfer->to_user_id])->save();
            }

            $this->log($activity, $actor, 'pic', null, null, $approve
                ? "Penanggung jawab dipindahkan dari {$transfer->fromUser->name} ke {$transfer->toUser->name}."
                : "Permintaan pindah penanggung jawab ke {$transfer->toUser->name} ditolak.".($note ? " {$note}" : ''));
            $this->logger->log($approve ? 'activity.pic_transferred' : 'activity.pic_transfer_rejected', $activity, [
                'from_user_id' => $transfer->from_user_id,
                'to_user_id' => $transfer->to_user_id,
            ], $actor);
        });

        $users = collect([$transfer->fromUser, $transfer->toUser])->filter()->unique('id');
        $this->notify($users, $activity, $approve ? 'Penanggung jawab kegiatan dipindahkan' : 'Pindah penanggung jawab ditolak',
            "{$activity->name}: {$transfer->fromUser->name} → {$transfer->toUser->name}");

        return $transfer;
    }

    // --- Kalender --------------------------------------------------------------------------

    public function calendarCategory(StudentActivity $activity): EventCategory
    {
        return $activity->organization?->isBem() ? EventCategory::Bem : EventCategory::Ukm;
    }

    private function publishToCalendar(StudentActivity $activity): void
    {
        $start = CarbonImmutable::parse($activity->starts_on)->startOfDay();
        $end = CarbonImmutable::parse($activity->ends_on)->startOfDay();
        $category = $this->calendarCategory($activity);

        $event = Event::query()->create([
            'title' => $activity->name,
            'description' => $activity->description,
            'category' => $category,
            'visibility' => $category->visibility(),
            'division_id' => null,
            'starts_at' => $start,
            'ends_at' => $end->greaterThan($start) ? $end : null,
            'all_day' => true,
            'location' => $activity->location,
            'organizer' => $activity->organization->name,
        ]);

        $activity->forceFill(['event_id' => $event->getKey()])->save();
        $this->conflicts->refresh([[$event->starts_at, $event->ends_at]], $event);
    }

    private function removeFromCalendar(StudentActivity $activity): void
    {
        $event = $activity->event;

        if ($event === null) {
            return;
        }

        $range = [$event->starts_at->copy(), $event->ends_at?->copy()];
        $activity->forceFill(['event_id' => null])->save();
        $event->delete();
        $this->conflicts->refresh([$range]);
    }

    // --- Internal --------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validatedDraft(User $actor, array $data): array
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:200'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'budget_estimate' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'proposal_path' => ['nullable', 'string', 'max:255'],
            'proposal_name' => ['nullable', 'string', 'max:255'],
        ], [], [
            'name' => 'nama kegiatan',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
            'budget_estimate' => 'perkiraan anggaran',
        ])->validate();

        // Organisasi tidak pernah dari input: pengaju → organisasinya; pengurus BEM → BEM UBHINUS.
        if ($actor->isApplicant()) {
            $validated['organization_id'] = $actor->organization_id;
            $validated['division_id'] = null;
        } else {
            $validated['organization_id'] = Organization::bem()?->getKey()
                ?? throw ValidationException::withMessages(['organization_id' => 'Organisasi BEM belum terdaftar.']);
            $validated['division_id'] = isset($validated['division_id']) && Division::query()->whereKey($validated['division_id'])->exists()
                ? (int) $validated['division_id'] : null;
        }

        if (filled($validated['proposal_path'] ?? null) && ! str_starts_with((string) $validated['proposal_path'], StudentActivity::DIRECTORY.'/')) {
            $validated['proposal_path'] = null;
        }

        return $validated;
    }

    /** @param  array<string, mixed>  $attributes */
    private function transition(User $actor, StudentActivity $activity, ActivityStatus $to, string $stage, ?string $note, array $attributes = []): StudentActivity
    {
        return DB::transaction(function () use ($actor, $activity, $to, $stage, $note, $attributes): StudentActivity {
            $from = $activity->status;
            $activity->forceFill([...$attributes, 'status' => $to])->save();

            $this->log($activity, $actor, $stage, $from, $to, $note);
            $this->logger->log('activity.status_changed', $activity, [
                'name' => $activity->name,
                'from' => $from->value,
                'to' => $to->value,
            ], $actor);

            return $activity;
        });
    }

    private function log(StudentActivity $activity, User $actor, string $stage, ?ActivityStatus $from, ?ActivityStatus $to, ?string $note): void
    {
        $activity->logs()->create([
            'stage' => $stage,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'user_id' => $actor->getKey(),
        ]);
    }

    /** Setiap tahap harus punya minimal satu penyetuju yang bukan penanggung jawab/pembuat. */
    private function ensureReviewersAvailable(StudentActivity $activity): void
    {
        foreach (['secretary' => 'Sekretaris', 'treasurer' => 'Bendahara', 'leader' => 'Ketua/Wakil'] as $step => $label) {
            if ($this->reviewersFor($activity, $step)->isEmpty()) {
                throw ValidationException::withMessages([
                    'reviewer' => "Tidak ada penyetuju aktif untuk tahap {$label} selain penanggung jawab/pembuat kegiatan ini. Hubungi Ketua/Wakil BEM.",
                ]);
            }
        }
    }

    /** Penyetuju tahap: pemegang perannya, atau super_admin sebagai cadangan; selain pihak terkait. */
    private function reviewersFor(StudentActivity $activity, string $step): Collection
    {
        $involved = array_filter([$activity->pic_user_id, $activity->created_by]);
        $superAdmins = User::query()->active()->role(RoleName::SuperAdmin->value)->whereNotIn('users.id', $involved)->get();

        if ($step === 'leader') {
            return $superAdmins;
        }

        $permission = $step === 'secretary' ? 'submission.verify' : 'submission.review_budget';
        $primary = User::query()->active()->permission($permission)->withoutRole(RoleName::SuperAdmin->value)
            ->whereNotIn('users.id', $involved)->get();

        return $primary->isNotEmpty() ? $primary : $superAdmins;
    }

    private function notifyStep(StudentActivity $activity, string $title): void
    {
        $step = $activity->status->reviewStep();

        if ($step !== null) {
            $this->notify($this->reviewersFor($activity, $step), $activity, $title, "{$activity->name} · {$activity->organization->name}");
        }
    }

    private function notifyPic(StudentActivity $activity, string $title, string $body): void
    {
        $this->notify(collect([$activity->pic])->filter(), $activity, $title, $body);
    }

    private function usersWith(string $permission): Collection
    {
        return User::query()->active()->permission($permission)->withoutRole(RoleName::SuperAdmin->value)->get();
    }

    private function notify(Collection $users, StudentActivity $activity, string $title, string $body, string $kind = 'status'): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users, new ActivityNotification($activity, $title, $body, $kind));
        }
    }

    private function reviewDeniedMessage(User $actor, StudentActivity $activity): string
    {
        return in_array($actor->getKey(), array_filter([$activity->pic_user_id, $activity->created_by]), true)
            ? 'Anda tidak boleh mereview kegiatan yang Anda ajukan/pegang sendiri.'
            : 'Anda tidak berhak memproses tahap review ini.';
    }

    private function authorize(User $actor, string $ability, StudentActivity $activity, string $message): void
    {
        if (Gate::forUser($actor)->denies($ability, $activity)) {
            throw new AuthorizationException($message);
        }
    }
}
