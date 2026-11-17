/**
 * Panel BEM (Fase 9): daftarkan service worker PWA + sambungan real-time chat (Laravel Echo + Pusher).
 *
 * Sinyal dari server TIDAK berisi isi pesan — hanya "percakapan X berubah". Halaman Chat lalu mengambil
 * isi lewat Livewire (lolos policy). Status koneksi dikirim ke Livewire: bila Pusher tidak tersedia /
 * terputus, halaman Chat otomatis memakai polling 5 detik.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const config = window.BEM_REALTIME || {};

function dispatch(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail }));

    if (window.Livewire) {
        window.Livewire.dispatch(name, detail);
    }
}

// ---------------------------------------------------------------- service worker (PWA)
if ('serviceWorker' in navigator && config.userId) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/panel-bem' }).catch(() => {});
    });
}

// ---------------------------------------------------------------- real-time chat
let connected = false;

function setConnected(value) {
    connected = value;
    dispatch('chat-realtime', { connected });
}

if (config.chat && config.pusher && config.pusher.key) {
    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'pusher',
        key: config.pusher.key,
        cluster: config.pusher.cluster,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                Accept: 'application/json',
            },
        },
    });

    window.BEM_ECHO = echo;

    const pusher = echo.connector.pusher;
    pusher.connection.bind('connected', () => setConnected(true));
    ['unavailable', 'failed', 'disconnected'].forEach((state) => pusher.connection.bind(state, () => setConnected(false)));

    echo.private(`chat.user.${config.userId}`)
        .listen('.chat.changed', (payload) => dispatch('chat-changed', payload))
        .error(() => setConnected(false));
} else {
    // Tanpa Pusher: beri tahu halaman Chat agar memakai polling.
    document.addEventListener('livewire:initialized', () => setConnected(false));
}

// Halaman Chat baru dimuat (navigasi SPA) → kirim ulang status koneksi terakhir.
document.addEventListener('livewire:navigated', () => dispatch('chat-realtime', { connected }));
window.addEventListener('chat-ready', () => dispatch('chat-realtime', { connected }));
