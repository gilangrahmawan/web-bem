<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CalendarFeedRequest;
use App\Models\Event;
use App\Support\CalendarFeed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/** Kalender eksternal /kalender: hanya kategori publik (hari besar, kampus, BEM, UKM). */
class CalendarController extends Controller
{
    public function index(): View
    {
        return view('public.calendar', [
            'categories' => EventCategory::publicCases(),
            'upcoming' => Event::query()->public()->upcoming()->orderBy('starts_at')->limit(8)->get(),
        ]);
    }

    public function feed(CalendarFeedRequest $request): JsonResponse
    {
        [$start, $end] = $request->range();

        $events = Event::query()
            ->public()
            ->between($start, $end)
            ->orderBy('starts_at')
            ->limit(500)
            ->get()
            ->map(fn (Event $event): array => CalendarFeed::toArray($event));

        return response()->json($events)->header('Cache-Control', 'public, max-age=300');
    }
}
