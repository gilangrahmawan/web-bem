<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\EventAssignee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Alur status tugas konten Medpub (AGENTS.md C7). Perpindahan status di dalam
 * transaction dan dicatat di activity_logs.
 */
class TaskService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** Penanggung jawab: Belum Dikerjakan → Dikerjakan. */
    public function start(User $actor, EventAssignee $assignee): EventAssignee
    {
        Gate::forUser($actor)->authorize('update', $assignee);

        return $this->transition($actor, $assignee, TaskStatus::InProgress);
    }

    /**
     * Penanggung jawab menyerahkan hasil (berkas yang sudah tersimpan di disk privat dan/atau link).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function submit(
        User $actor,
        EventAssignee $assignee,
        ?string $resultPath,
        ?string $originalName,
        ?string $resultUrl,
    ): EventAssignee {
        Gate::forUser($actor)->authorize('update', $assignee);

        Validator::make(
            ['result_url' => $resultUrl],
            ['result_url' => ['nullable', 'url:http,https', 'max:500']],
            attributes: ['result_url' => 'link hasil'],
        )->validate();

        $resultPath = filled($resultPath) ? $resultPath : null;
        $resultUrl = filled($resultUrl) ? $resultUrl : null;

        if ($resultPath === null && $resultUrl === null && blank($assignee->result_path)) {
            throw ValidationException::withMessages(['result' => 'Unggah berkas hasil atau isi link hasil.']);
        }

        if (! $assignee->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Tugas ini sudah diserahkan atau selesai.']);
        }

        return DB::transaction(function () use ($actor, $assignee, $resultPath, $originalName, $resultUrl): EventAssignee {
            $from = $assignee->status;

            $assignee->fill([
                'status' => TaskStatus::Submitted,
                'result_url' => $resultUrl,
                'submitted_at' => now(),
            ]);

            if ($resultPath !== null) {
                $assignee->fill(['result_path' => $resultPath, 'result_original_name' => $originalName]);
            }

            $assignee->save();

            $this->logger->log('task.submitted', $assignee, [
                'event_id' => $assignee->event_id,
                'from' => $from->value,
                'to' => TaskStatus::Submitted->value,
                'file' => $assignee->result_original_name,
                'url' => $assignee->result_url,
            ], $actor);

            return $assignee;
        });
    }

    /** Penanggung jawab menarik kembali penyerahan: Diserahkan → Dikerjakan. */
    public function withdraw(User $actor, EventAssignee $assignee): EventAssignee
    {
        Gate::forUser($actor)->authorize('update', $assignee);

        if ($assignee->status !== TaskStatus::Submitted) {
            throw ValidationException::withMessages(['status' => 'Hanya tugas berstatus Diserahkan yang bisa ditarik kembali.']);
        }

        return $this->transition($actor, $assignee, TaskStatus::InProgress);
    }

    /** Koordinator Medpub: Diserahkan → Selesai, atau Diserahkan/Selesai → Dikerjakan (dikembalikan). */
    public function review(User $actor, EventAssignee $assignee, TaskStatus $to): EventAssignee
    {
        Gate::forUser($actor)->authorize('review', $assignee);

        if (! in_array($to, [TaskStatus::Done, TaskStatus::InProgress], true)) {
            throw ValidationException::withMessages(['status' => 'Status review tidak valid.']);
        }

        if ($to === TaskStatus::InProgress && $assignee->status === TaskStatus::Todo) {
            throw ValidationException::withMessages(['status' => 'Tugas belum diserahkan.']);
        }

        return $this->transition($actor, $assignee, $to, 'task.reviewed');
    }

    private function transition(User $actor, EventAssignee $assignee, TaskStatus $to, string $action = 'task.status_changed'): EventAssignee
    {
        $from = $assignee->status;

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Status tidak bisa diubah dari {$from->label()} ke {$to->label()}.",
            ]);
        }

        return DB::transaction(function () use ($actor, $assignee, $from, $to, $action): EventAssignee {
            $assignee->update(['status' => $to]);

            $this->logger->log($action, $assignee, [
                'event_id' => $assignee->event_id,
                'from' => $from->value,
                'to' => $to->value,
            ], $actor);

            return $assignee;
        });
    }
}
