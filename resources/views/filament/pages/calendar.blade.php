<x-filament-panels::page>
    <div data-panel-calendar-root wire:ignore class="bem-calendar">
        <div class="bem-calendar__filters">
            <fieldset class="bem-calendar__categories">
                <legend class="bem-calendar__label">Kategori</legend>
                @foreach ($categories as $category)
                    <label class="bem-calendar__chip">
                        <input type="checkbox" value="{{ $category->value }}" data-filter-category checked>
                        <span class="bem-calendar__dot" style="background: {{ $category->hex() }}"></span>
                        {{ $category->label() }}
                    </label>
                @endforeach
            </fieldset>

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
            Tanda ⚠ = tanggal bentrok dengan agenda UKM/BEM lain.
        </p>

        <div data-panel-calendar data-can-create="{{ $canCreate ? '1' : '0' }}"></div>

        @vite('resources/js/panel-calendar.js')
    </div>

    <style>
        .bem-calendar { display: grid; gap: 1rem; }
        .bem-calendar__filters { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; justify-content: space-between; }
        .bem-calendar__categories { display: flex; flex-wrap: wrap; gap: .5rem; border: 0; padding: 0; margin: 0; }
        .bem-calendar__label { display: block; width: 100%; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: .25rem; }
        .bem-calendar__chip { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .7rem; border-radius: 999px; font-size: .8125rem; cursor: pointer; border: 1px solid rgb(148 163 184 / .4); }
        .bem-calendar__chip input { accent-color: #232361; }
        .bem-calendar__dot { width: .65rem; height: .65rem; border-radius: 999px; display: inline-block; }
        .bem-calendar__select { border-radius: .5rem; border: 1px solid rgb(148 163 184 / .5); padding: .4rem 2rem .4rem .6rem; font-size: .875rem; background-color: transparent; color: inherit; }
        .bem-calendar__select option { color: #0f172a; }
        .bem-calendar__hint { font-size: .8125rem; opacity: .7; margin: 0; }
        .bem-calendar .fc { --fc-border-color: rgb(148 163 184 / .35); --fc-today-bg-color: rgb(255 219 26 / .18); --fc-button-bg-color: #232361; --fc-button-border-color: #232361; --fc-button-hover-bg-color: #000078; --fc-button-hover-border-color: #000078; --fc-button-active-bg-color: #000078; --fc-button-active-border-color: #000078; font-size: .875rem; }
        .bem-calendar .fc .fc-toolbar-title { font-size: 1.15rem; font-weight: 700; }
        .bem-calendar .fc-event { cursor: pointer; }
        .dark .bem-calendar .fc { --fc-page-bg-color: transparent; --fc-neutral-bg-color: rgb(255 255 255 / .05); --fc-list-event-hover-bg-color: rgb(255 255 255 / .06); }
        .dark .bem-calendar .fc .fc-list-day-cushion { background: rgb(255 255 255 / .05); }
    </style>
</x-filament-panels::page>
