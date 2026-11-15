<?php

namespace App\Services;

use App\Enums\EventCategory;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deteksi bentrok agenda UKM/BEM (AGENTS.md C4).
 * Keputusan pemilik: bentrok dihitung PER TANGGAL — dua agenda yang tanggalnya
 * beririsan dianggap bentrok walau jamnya berbeda.
 */
class EventConflictService
{
    /**
     * Agenda UKM/BEM lain yang tanggalnya beririsan dengan rentang ini.
     *
     * @return Collection<int, Event>
     */
    public function findConflicts(
        EventCategory $category,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        ?int $ignoreId = null,
    ): Collection {
        if (! $category->checksConflict()) {
            return new Collection;
        }

        [$from, $to] = $this->dayRange($start, $end);

        return Event::query()
            ->whereIn('category', $this->conflictCategoryValues())
            ->between($from, $to)
            ->when($ignoreId, fn ($q, int $id) => $q->whereKeyNot($id))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Hitung ulang has_conflict untuk agenda $event (bila ada) dan semua agenda UKM/BEM
     * yang menyentuh rentang tanggal yang diberikan (rentang baru & lama saat diubah/dihapus).
     *
     * @param  list<array{0: CarbonInterface, 1: ?CarbonInterface}>  $ranges
     */
    public function refresh(array $ranges, ?Event $event = null): void
    {
        $candidates = new Collection;

        foreach ($ranges as [$start, $end]) {
            [$from, $to] = $this->dayRange($start, $end);

            $candidates = $candidates->merge(
                Event::query()->whereIn('category', $this->conflictCategoryValues())->between($from, $to)->get()
            );
        }

        if ($event?->exists) {
            $candidates->push($event);
        }

        $candidates->unique('id')->each(function (Event $candidate): void {
            $hasConflict = $this->findConflicts(
                $candidate->category,
                $candidate->starts_at,
                $candidate->ends_at,
                $candidate->getKey(),
            )->isNotEmpty();

            if ($candidate->has_conflict !== $hasConflict) {
                $candidate->forceFill(['has_conflict' => $hasConflict])->saveQuietly();
            }
        });
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    private function dayRange(CarbonInterface $start, ?CarbonInterface $end): array
    {
        $end ??= $start;

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    /** @return list<string> */
    private function conflictCategoryValues(): array
    {
        return array_map(fn (EventCategory $c): string => $c->value, EventCategory::conflictCases());
    }
}
