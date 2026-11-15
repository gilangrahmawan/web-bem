/*
| Kalender publik /kalender. Data dari endpoint JSON (hanya kategori publik).
| Detail agenda tampil di <dialog>; semua teks diisi dengan textContent.
*/
import { createCalendar, safeHttpUrl } from './calendar';

function init() {
    const el = document.querySelector('[data-public-calendar]');

    if (!el) {
        return;
    }

    const feedUrl = el.dataset.feedUrl;
    const toggles = [...document.querySelectorAll('[data-category-toggle]')];
    const activeCategories = () => new Set(toggles.filter((t) => t.checked).map((t) => t.value));

    const calendar = createCalendar(el, {
        events: (info, success, failure) => {
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr });

            fetch(`${feedUrl}?${params}`, { headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    return response.json();
                })
                .then((list) => {
                    const active = activeCategories();
                    success(list.filter((event) => active.has(event.extendedProps.category)));
                })
                .catch(failure);
        },
        onEventClick: showDetail,
    });

    toggles.forEach((toggle) => toggle.addEventListener('change', () => calendar.refetchEvents()));
}

function showDetail(event) {
    const dialog = document.querySelector('[data-event-dialog]');

    if (!dialog) {
        return;
    }

    const props = event.extendedProps;
    const set = (name, value) => {
        const field = dialog.querySelector(`[data-field="${name}"]`);

        if (!field) {
            return;
        }

        field.textContent = value || '';
        field.closest('[data-row]')?.classList.toggle('hidden', !value);
    };

    set('title', event.title.replace(/^⚠\s*/, ''));
    set('category', props.categoryLabel);
    set('when', props.when);
    set('location', props.location);
    set('organizer', props.organizer);
    set('description', props.description);

    dialog.querySelector('[data-field="conflict"]')?.classList.toggle('hidden', !props.hasConflict);

    const link = dialog.querySelector('[data-field="registration"]');
    const href = safeHttpUrl(props.registrationUrl);

    if (link) {
        link.classList.toggle('hidden', !href);
        link.href = href || '#';
    }

    dialog.showModal();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
