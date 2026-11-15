/*
| Fabrik FullCalendar bersama untuk /kalender (publik) dan halaman Kalender di dashboard.
| CSS FullCalendar v6 disuntikkan otomatis oleh library (tidak perlu stylesheet terpisah).
*/
import { Calendar } from '@fullcalendar/core';
import idLocale from '@fullcalendar/core/locales/id';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';

const isMobile = () => window.matchMedia('(max-width: 640px)').matches;

/**
 * @param {HTMLElement} el
 * @param {{
 *   events: Function,
 *   onEventClick?: (event: object) => void,
 *   onSelect?: (info: {start: string, end: string, allDay: boolean}) => void,
 * }} options
 */
export function createCalendar(el, { events, onEventClick, onSelect }) {
    const mobile = isMobile();

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        locale: idLocale,
        initialView: mobile ? 'listMonth' : 'dayGridMonth',
        headerToolbar: mobile
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        footerToolbar: mobile ? { center: 'dayGridMonth,listMonth' } : false,
        buttonText: { today: 'Hari ini', month: 'Bulan', week: 'Minggu', list: 'Daftar' },
        height: 'auto',
        dayMaxEvents: 3,
        nowIndicator: true,
        eventDisplay: 'block',
        noEventsContent: 'Tidak ada agenda pada rentang ini.',
        events,
        selectable: Boolean(onSelect),
        selectLongPressDelay: 400,
        select: (info) => {
            calendar.unselect();
            onSelect?.({ start: info.startStr, end: info.endStr, allDay: info.allDay });
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            onEventClick?.(info.event);
        },
    });

    calendar.render();

    return calendar;
}

/** Hanya izinkan link http(s) (mencegah `javascript:` dll.). */
export function safeHttpUrl(value) {
    if (!value) {
        return null;
    }

    try {
        const url = new URL(value, window.location.origin);

        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
}
