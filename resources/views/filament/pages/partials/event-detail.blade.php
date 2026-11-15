@php
    use App\Support\CalendarFeed;
@endphp
@if ($event)
    <dl style="display: grid; gap: .75rem; font-size: .875rem;">
        <div style="display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;">
            <x-filament::badge :color="$event->category->color()">{{ $event->category->label() }}</x-filament::badge>
            @if ($event->division)
                <x-filament::badge color="gray">{{ $event->division->name }}</x-filament::badge>
            @endif
            @if ($event->has_conflict)
                <x-filament::badge color="warning">⚠ Bentrok</x-filament::badge>
            @endif
        </div>

        <div>
            <dt style="font-weight: 600;">Waktu</dt>
            <dd>{{ CalendarFeed::when($event) }}</dd>
        </div>

        @if ($event->location)
            <div>
                <dt style="font-weight: 600;">Lokasi</dt>
                <dd>{{ $event->location }}</dd>
            </div>
        @endif

        @if ($event->organizer)
            <div>
                <dt style="font-weight: 600;">Penyelenggara</dt>
                <dd>{{ $event->organizer }}</dd>
            </div>
        @endif

        @if ($event->registration_url)
            <div>
                <dt style="font-weight: 600;">Link pendaftaran</dt>
                <dd><a href="{{ $event->registration_url }}" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;">{{ $event->registration_url }}</a></dd>
            </div>
        @endif

        @if ($event->description)
            <div>
                <dt style="font-weight: 600;">Keterangan</dt>
                <dd style="white-space: pre-line;">{{ $event->description }}</dd>
            </div>
        @endif

        @if ($event->assignees->isNotEmpty())
            <div>
                <dt style="font-weight: 600;">Penanggung jawab</dt>
                <dd>
                    <ul style="display: grid; gap: .35rem; margin-top: .25rem;">
                        @foreach ($event->assignees as $assignee)
                            <li style="display: flex; gap: .5rem; align-items: center;">
                                <span>{{ $assignee->user?->name }}</span>
                                <x-filament::badge :color="$assignee->status->color()" size="sm">{{ $assignee->status->label() }}</x-filament::badge>
                            </li>
                        @endforeach
                    </ul>
                </dd>
            </div>
        @endif
    </dl>
@else
    <p>Agenda tidak ditemukan.</p>
@endif
