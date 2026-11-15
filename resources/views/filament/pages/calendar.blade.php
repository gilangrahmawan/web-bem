<x-filament-panels::page>
    <div data-panel-calendar-root wire:ignore class="bem-calendar">
        <x-calendar-legend :categories="$categories" toggle-attribute="data-filter-category" />

        <div class="bem-calendar__filters">
            <label class="bem-calendar__division">
                <span class="bem-calendar__label">Divisi</span>
                <select data-filter-division class="bem-calendar__select">
                    <option value="">Semua agenda</option>
                    @foreach ($divisions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <p class="bem-calendar__hint">
            @if ($canCreate)
                Klik atau seret tanggal untuk menambah agenda. Klik agenda untuk melihat detail atau mengubahnya.
            @else
                Klik agenda untuk melihat detail.
            @endif
        </p>

        <div data-panel-calendar data-can-create="{{ $canCreate ? '1' : '0' }}"></div>

        @vite('resources/js/panel-calendar.js')
    </div>

    <style>
        .bem-calendar { display: grid; gap: 1rem; }
        .bem-calendar__filters { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; justify-content: space-between; }
        .bem-calendar__label { display: block; width: 100%; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: .25rem; }
        .bem-calendar__select { border-radius: .5rem; border: 1px solid rgb(148 163 184 / .5); padding: .4rem 2rem .4rem .6rem; font-size: .875rem; background-color: transparent; color: inherit; }
        .bem-calendar__select option { color: #0f172a; }
        .bem-calendar__hint { font-size: .8125rem; opacity: .7; margin: 0; }
        .bem-calendar .fc { --fc-border-color: rgb(148 163 184 / .35); --fc-today-bg-color: rgb(255 219 26 / .18); --fc-button-bg-color: #232361; --fc-button-border-color: #232361; --fc-button-hover-bg-color: #000078; --fc-button-hover-border-color: #000078; --fc-button-active-bg-color: #000078; --fc-button-active-border-color: #000078; font-size: .875rem; }
        .bem-calendar .fc .fc-toolbar-title { font-size: 1.15rem; font-weight: 700; }
        .bem-calendar .fc-event { cursor: pointer; font-weight: 600; }
        /* Sel hari (legenda BEM): Minggu & tanggal Libur Nasional merah muda, Jumat hijau muda. */
        .bem-calendar .fc .bem-day--friday { background: rgb(147 247 171 / .35); }
        .bem-calendar .fc .bem-day--sunday, .bem-calendar .fc .bem-day--holiday { background: rgb(255 171 171 / .35); }
        .bem-calendar .fc .bem-day--sunday .fc-daygrid-day-number,
        .bem-calendar .fc .bem-day--holiday .fc-daygrid-day-number,
        .bem-calendar .fc .fc-col-header-cell.fc-day-sun { color: #C00000; font-weight: 700; }
        .bem-calendar .fc .fc-day-today { box-shadow: inset 0 0 0 2px #ffdb1a; }
        .dark .bem-calendar .fc .bem-day--sunday .fc-daygrid-day-number,
        .dark .bem-calendar .fc .bem-day--holiday .fc-daygrid-day-number,
        .dark .bem-calendar .fc .fc-col-header-cell.fc-day-sun { color: #FF8A8A; }
        .dark .bem-calendar .fc { --fc-page-bg-color: transparent; --fc-neutral-bg-color: rgb(255 255 255 / .05); --fc-list-event-hover-bg-color: rgb(255 255 255 / .06); }
        .dark .bem-calendar .fc .fc-list-day-cushion { background: rgb(255 255 255 / .05); }
    </style>
</x-filament-panels::page>
