<?php

namespace App\Services;

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\EventAssignee;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Policies\EventPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Simpan/ubah/hapus agenda kalender. Semua jalur (dashboard, test, nanti API) lewat sini:
 * otorisasi diulang di backend, divisi dipaksa sesuai hak, visibility diturunkan dari kategori,
 * has_conflict diperbarui, dan perubahan dicatat di activity_logs.
 */
class EventService
{
    public function __construct(
        private readonly EventConflictService $conflicts,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function create(User $actor, array $data): Event
    {
        $attributes = $this->normalize($actor, $data);
        $assigneeIds = $this->assigneeIds($attributes['category'], $data);

        return DB::transaction(function () use ($actor, $attributes, $assigneeIds): Event {
            $event = Event::query()->create([...$attributes, 'created_by' => $actor->getKey()]);

            $newAssignees = $this->syncAssignees($event, $assigneeIds);
            $this->conflicts->refresh([[$event->starts_at, $event->ends_at]], $event);

            $this->logger->log('event.created', $event, [
                'title' => $event->title,
                'category' => $event->category->value,
                'starts_at' => $event->starts_at->toDateTimeString(),
            ], $actor);

            DB::afterCommit(fn () => $this->notifyAssigned($newAssignees));

            return $event->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function update(User $actor, Event $event, array $data): Event
    {
        Gate::forUser($actor)->authorize('update', $event);

        $attributes = $this->normalize($actor, $data);
        $assigneeIds = $this->assigneeIds($attributes['category'], $data);
        $oldRange = [$event->starts_at->copy(), $event->ends_at?->copy()];

        return DB::transaction(function () use ($actor, $event, $attributes, $assigneeIds, $oldRange): Event {
            $event->fill($attributes);
            $changes = array_keys($event->getDirty());
            $event->save();

            $newAssignees = $this->syncAssignees($event, $assigneeIds);
            $this->conflicts->refresh([$oldRange, [$event->starts_at, $event->ends_at]], $event);

            $this->logger->log('event.updated', $event, [
                'title' => $event->title,
                'changed' => $changes,
            ], $actor);

            DB::afterCommit(fn () => $this->notifyAssigned($newAssignees));

            return $event->refresh();
        });
    }

    /** @throws AuthorizationException */
    public function delete(User $actor, Event $event): void
    {
        Gate::forUser($actor)->authorize('delete', $event);

        DB::transaction(function () use ($actor, $event): void {
            $range = [$event->starts_at->copy(), $event->ends_at?->copy()];

            // Lewat model agar berkas hasil tugas ikut terhapus dari disk.
            $event->assignees()->get()->each->delete();
            $event->delete();

            $this->conflicts->refresh([$range]);

            $this->logger->log('event.deleted', null, [
                'event_id' => $event->getKey(),
                'title' => $event->title,
                'category' => $event->category->value,
                'starts_at' => $event->starts_at->toDateTimeString(),
            ], $actor);
        });
    }

    /**
     * Validasi + normalisasi data dari form. Field yang tidak berhak diisi diabaikan/dipaksa.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws AuthorizationException|ValidationException
     */
    private function normalize(User $actor, array $data): array
    {
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(EventCategory::class)],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'all_day' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:200'],
            'organizer' => ['nullable', 'string', 'max:150'],
            'registration_url' => ['nullable', 'url:http,https', 'max:500'],
        ], attributes: [
            'title' => 'judul',
            'starts_at' => 'tanggal mulai',
            'ends_at' => 'tanggal selesai',
            'registration_url' => 'link pendaftaran',
        ])->validate();

        $category = EventCategory::from($validated['category']);

        Gate::forUser($actor)->authorize('create', [Event::class, $category]);

        $divisionId = $this->resolveDivision($actor, $category, $validated['division_id'] ?? null);

        $allDay = (bool) ($validated['all_day'] ?? true);
        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = filled($validated['ends_at'] ?? null) ? Carbon::parse($validated['ends_at']) : null;

        if ($allDay) {
            $startsAt = $startsAt->startOfDay();
            $endsAt = $endsAt?->startOfDay();
        }

        if ($endsAt !== null && $endsAt->equalTo($startsAt)) {
            $endsAt = null;
        }

        return [
            'title' => trim($validated['title']),
            'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'category' => $category,
            'visibility' => $category->visibility(),
            'division_id' => $divisionId,
            'all_day' => $allDay,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'location' => $validated['location'] ?? null,
            'organizer' => $validated['organizer'] ?? null,
            // Link pendaftaran hanya relevan untuk agenda publik.
            'registration_url' => $category->isPublic() ? ($validated['registration_url'] ?? null) : null,
        ];
    }

    /** @throws AuthorizationException */
    private function resolveDivision(User $actor, EventCategory $category, ?int $requested): ?int
    {
        if (! $category->isDivisionScoped()) {
            return null;
        }

        // Koordinator: selalu divisinya sendiri, apa pun yang dikirim form.
        $divisionId = EventPolicy::managesAllDivisions($actor) ? $requested : $actor->division_id;

        if (! EventPolicy::canUseDivision($actor, $category, $divisionId)) {
            throw new AuthorizationException('Anda hanya boleh mengelola agenda divisi Anda sendiri.');
        }

        return $divisionId;
    }

    /**
     * Hanya tugas konten yang punya penanggung jawab; hanya pengurus aktif.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function assigneeIds(EventCategory $category, array $data): array
    {
        if ($category !== EventCategory::Content) {
            return [];
        }

        $requested = collect($data['assignee_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values();

        $ids = User::query()->active()->whereKey($requested)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'Pilih minimal satu penanggung jawab aktif untuk tugas konten.',
            ]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<EventAssignee> penanggung jawab yang baru ditambahkan
     */
    private function syncAssignees(Event $event, array $userIds): array
    {
        $event->assignees()->whereNotIn('user_id', $userIds)->get()->each->delete();

        $existing = $event->assignees()->pluck('user_id')->map(fn ($id): int => (int) $id)->all();

        $new = [];

        foreach (array_diff($userIds, $existing) as $userId) {
            $new[] = $event->assignees()->create(['user_id' => $userId]);
        }

        return $new;
    }

    /** @param  list<EventAssignee>  $assignees */
    private function notifyAssigned(array $assignees): void
    {
        foreach ($assignees as $assignee) {
            $assignee->user->notify(new TaskAssignedNotification($assignee));
        }
    }
}
