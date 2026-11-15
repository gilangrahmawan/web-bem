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

    // Tanggal (YYYY-MM-DD) yang punya agenda Libur Nasional → diberi latar seperti hari Minggu.
    let holidayDates = new Set();

    const dayClasses = (date) => {
        const classes = [];
        const dow = date.getDay();

        if (dow === 0) {
            classes.push('bem-day--sunday');
        } else if (dow === 5) {
            classes.push('bem-day--friday');
        }

        if (holidayDates.has(ymd(date))) {
            classes.push('bem-day--holiday');
        }

        return classes;
    };

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
        dayCellClassNames: (arg) => dayClasses(arg.date),
        // Garis kiri tebal sesuai legenda (Kalender Akademik, Kegiatan BEM). Tidak untuk tampilan daftar.
        eventDidMount: (info) => {
            const accent = info.event.extendedProps.accent;

            if (accent && !info.el.classList.contains('fc-list-event')) {
                info.el.style.borderLeft = `4px solid ${accent}`;
            }
        },
        // Setelah data dimuat/difilter: tandai ulang tanggal Libur Nasional yang sedang tampil.
        eventsSet: (list) => {
            holidayDates = holidayDatesOf(list);

            el.querySelectorAll('[data-date]').forEach((cell) => {
                if (cell.matches('.fc-daygrid-day, .fc-timegrid-col')) {
                    cell.classList.toggle('bem-day--holiday', holidayDates.has(cell.dataset.date));
                }
            });
        },
    });

    calendar.render();

    return calendar;
}

/** "YYYY-MM-DD" dari komponen tanggal lokal. */
function ymd(date) {
    const pad = (n) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Semua tanggal yang dicakup agenda kategori `holiday` (akhir agenda seharian bersifat eksklusif). */
function holidayDatesOf(list) {
    const dates = new Set();

    list.filter((event) => event.extendedProps.category === 'holiday' && event.start).forEach((event) => {
        const day = new Date(event.start.getFullYear(), event.start.getMonth(), event.start.getDate());
        const end = event.end ?? new Date(day.getTime() + 86400000);

        for (let i = 0; day < end && i < 400; i++) {
            dates.add(ymd(day));
            day.setDate(day.getDate() + 1);
        }
    });

    return dates;
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
