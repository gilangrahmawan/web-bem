{{--
    Service worker + koneksi real-time chat (Fase 9). Kunci publik Pusher diambil dari konfigurasi server
    (bukan VITE_*), jadi tidak perlu build ulang saat pindah hosting. Pembina & yang tidak bisa chat tidak
    tersambung ke Pusher. Bila Pusher tidak dikonfigurasi, halaman Chat memakai polling 5 detik.
--}}
@php
    $user = auth()->user();
    $pusher = config('broadcasting.default') === 'pusher' && filled(config('broadcasting.connections.pusher.key'));
    $realtime = [
        'userId' => $user?->getKey(),
        'chat' => $user instanceof \App\Models\User && $user->canUseChat(),
        'pusher' => $pusher ? [
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
        ] : null,
    ];
@endphp
<script>window.BEM_REALTIME = @js($realtime);</script>
@vite('resources/js/panel-realtime.js')
