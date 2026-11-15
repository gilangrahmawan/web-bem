/*
| Kalender dashboard (halaman Filament "Kalender").
| Data diambil lewat method Livewire fetchEvents (sudah difilter hak akses di server).
| Klik/seret tanggal → aksi "Tambah agenda"; klik agenda → aksi ubah/lihat.
| Server tetap memeriksa izin setiap aksi; data di sini hanya untuk tampilan.
*/
import { createCalendar } from './calendar';

function mount(el) {
    if (el.dataset.mounted === '1') {
        return;
    }

    const host = el.closest('[wire\\:id]');
    const $wire = host ? window.Livewire?.find(host.getAttribute('wire:id')) : null;

    if (!$wire) {
        return;
    }

    el.dataset.mounted = '1';

    const root = el.closest('[data-panel-calendar-root]') ?? document;
    const categoryInputs = [...root.querySelectorAll('[data-filter-category]')];
    const divisionSelect = root.querySelector('[data-filter-division]');
    const canCreate = el.dataset.canCreate === '1';

    const calendar = createCalendar(el, {
        events: (info, success, failure) => {
            const categories = categoryInputs.filter((input) => input.checked).map((input) => input.value);
            const division = divisionSelect?.value ? Number(divisionSelect.value) : null;

            if (categories.length === 0) {
                success([]);

                return;
            }

            $wire.fetchEvents(info.startStr, info.endStr, categories, division).then(success).catch(failure);
        },
        onSelect: canCreate
            ? (range) => $wire.mountAction('createEvent', range)
            : undefined,
        onEventClick: (event) => {
            $wire.mountAction(event.extendedProps.canEdit ? 'editEvent' : 'viewEvent', { id: Number(event.id) });
        },
    });

    categoryInputs.forEach((input) => input.addEventListener('change', () => calendar.refetchEvents()));
    divisionSelect?.addEventListener('change', () => calendar.refetchEvents());
    window.addEventListener('bem-calendar-refresh', () => calendar.refetchEvents());
}

function mountAll() {
    document.querySelectorAll('[data-panel-calendar]').forEach(mount);
}

// Skrip modul bisa berjalan sebelum atau sesudah Livewire siap.
if (window.Livewire?.find) {
    mountAll();
}

document.addEventListener('livewire:initialized', mountAll);
document.addEventListener('livewire:navigated', mountAll);
