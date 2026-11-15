@php
    use App\Support\CalendarFeed;

    $category = $event->category;
@endphp
<li class="flex gap-4 py-4 first:pt-0 last:pb-0">
    <div class="flex w-14 shrink-0 flex-col items-center justify-center rounded-xl py-2"
        style="background: {{ $category->background() }}; color: {{ $category->text() }};{{ $category->accent() ? ' border-left: 4px solid '.$category->accent().';' : '' }}">
        <span class="text-xl leading-none font-extrabold">{{ $event->starts_at->format('j') }}</span>
        <span class="mt-1 text-[0.6875rem] font-semibold tracking-wide uppercase">{{ $event->starts_at->locale('id')->translatedFormat('M') }}</span>
    </div>
    <div class="min-w-0">
        <p class="text-xs font-semibold text-brand-secondary">
            {{ $category->label() }}
            @if ($event->has_conflict)
                <span class="ml-1 text-amber-700" title="Tanggal bentrok dengan agenda lain">⚠ bentrok</span>
            @endif
        </p>
        <p class="mt-0.5 font-bold text-slate-900">{{ $event->title }}</p>
        <p class="mt-0.5 text-sm text-slate-600">
            {{ CalendarFeed::when($event) }}@if ($event->location) · {{ $event->location }}@endif
        </p>
    </div>
</li>
