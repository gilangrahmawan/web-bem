<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Mengubah Event menjadi objek event FullCalendar (publik & dashboard).
 * Semua teks dirender dengan textContent di JavaScript (bukan innerHTML).
 */
class CalendarFeed
{
    /** Rentang maksimal satu permintaan data kalender. */
    public const MAX_RANGE_DAYS = 100;

    /**
     * @param  array<string, mixed>  $extra  extendedProps tambahan (mis. hak ubah di dashboard)
     * @return array<string, mixed>
     */
    public static function toArray(Event $event, bool $internal = false, array $extra = []): array
    {
        $category = $event->category;
        $end = $event->effectiveEnd();

        return [
            'id' => (string) $event->getKey(),
            'title' => ($event->has_conflict ? '⚠ ' : '').$event->title,
            'start' => $event->all_day ? $event->starts_at->toDateString() : $event->starts_at->toIso8601String(),
            // FullCalendar memakai akhir eksklusif untuk agenda seharian.
            'end' => $event->all_day
                ? $end->copy()->addDay()->toDateString()
                : $event->ends_at?->toIso8601String(),
            'allDay' => $event->all_day,
            'backgroundColor' => $category->background(),
            // Agenda bentrok diberi bingkai amber (selain tanda ⚠ di judul).
            'borderColor' => $event->has_conflict ? '#D97706' : ($category->accent() ?? $category->background()),
            'textColor' => $category->text(),
            'classNames' => ['bem-ev', 'bem-ev--'.$category->value],
            'extendedProps' => [
                'category' => $category->value,
                'categoryLabel' => $category->label(),
                // Garis kiri tebal (campus, bem) dipasang lewat eventDidMount di calendar.js.
                'accent' => $category->accent(),
                'when' => self::when($event),
                'location' => $event->location,
                'organizer' => $event->organizer,
                'description' => $event->description,
                'registrationUrl' => $event->registration_url,
                'hasConflict' => $event->has_conflict,
                ...($internal ? ['division' => $event->division?->name] : []),
                ...$extra,
            ],
        ];
    }

    /** "15 November 2026", "15–17 November 2026", atau "15 November 2026, 19.00". */
    public static function when(Event $event): string
    {
        $start = $event->starts_at;
        $end = $event->ends_at;

        if ($event->all_day) {
            if ($end === null || $end->isSameDay($start)) {
                return Format::date($start);
            }

            return Format::date($start).' – '.Format::date($end);
        }

        $text = Format::date($start).', '.$start->format('H.i');

        if ($end !== null) {
            $text .= ' – '.($end->isSameDay($start) ? $end->format('H.i') : Format::date($end).', '.$end->format('H.i'));
        }

        return $text.' WIB';
    }

    /**
     * Parse rentang dari FullCalendar; null bila tidak valid atau melebihi batas.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public static function parseRange(?string $start, ?string $end): ?array
    {
        try {
            $from = Carbon::parse((string) $start)->timezone(config('app.timezone'));
            $to = Carbon::parse((string) $end)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        if ($to->lessThanOrEqualTo($from) || $from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return null;
        }

        return [$from, $to];
    }
}
