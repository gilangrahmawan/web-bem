@php
    use App\Support\CalendarFeed;
@endphp
<ul style="display: grid; gap: .5rem; font-size: .875rem;">
    @forelse ($conflicts as $conflict)
        <li style="padding: .6rem .75rem; border-radius: .5rem; border: 1px solid rgb(245 158 11 / .45); background: rgb(245 158 11 / .08);">
            <div style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <x-filament::badge :color="$conflict->category->color()" size="sm">{{ $conflict->category->label() }}</x-filament::badge>
                <strong>{{ $conflict->title }}</strong>
            </div>
            <div style="opacity: .75; margin-top: .2rem;">
                {{ CalendarFeed::when($conflict) }}@if ($conflict->organizer) · {{ $conflict->organizer }}@endif
            </div>
        </li>
    @empty
        <li>Tidak ada agenda yang bentrok.</li>
    @endforelse
</ul>
