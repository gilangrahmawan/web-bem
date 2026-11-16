<x-filament-panels::page>
    <div class="bem-notif"
        x-data="{
            supported: ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window),
            permission: ('Notification' in window) ? Notification.permission : 'unsupported',
            subscribed: false,
            busy: false,
            message: '',
            standalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
            ios: /iphone|ipad|ipod/i.test(navigator.userAgent),
            async init() {
                if (! this.supported) { return }
                const reg = await navigator.serviceWorker.getRegistration('/panel-bem/')
                const sub = reg ? await reg.pushManager.getSubscription() : null
                this.subscribed = Boolean(sub)
            },
            key(base64) {
                const padding = '='.repeat((4 - base64.length % 4) % 4)
                const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
                return Uint8Array.from([...raw].map(c => c.charCodeAt(0)))
            },
            async enable() {
                this.busy = true; this.message = ''
                try {
                    this.permission = await Notification.requestPermission()
                    if (this.permission !== 'granted') { this.message = 'Izin notifikasi ditolak. Ubah di pengaturan situs browser.'; return }
                    const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/panel-bem/' })
                    await navigator.serviceWorker.ready
                    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.key(@js($vapidPublicKey)) })
                    const res = await fetch(@js(route('panel.push.subscribe')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ ...sub.toJSON(), contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] }),
                    })
                    if (! res.ok) { throw new Error('server') }
                    this.subscribed = true
                    this.message = 'Notifikasi aktif di perangkat ini.'
                    $wire.$refresh()
                } catch (e) {
                    this.message = 'Gagal mengaktifkan notifikasi di perangkat ini.'
                } finally { this.busy = false }
            },
            async disable() {
                this.busy = true
                try {
                    const reg = await navigator.serviceWorker.getRegistration('/panel-bem/')
                    const sub = reg ? await reg.pushManager.getSubscription() : null
                    if (sub) {
                        await fetch(@js(route('panel.push.unsubscribe')), {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ endpoint: sub.endpoint }),
                        })
                        await sub.unsubscribe()
                    }
                    this.subscribed = false
                    this.message = 'Notifikasi dimatikan di perangkat ini.'
                    $wire.$refresh()
                } finally { this.busy = false }
            },
        }">

        <x-filament::section>
            <x-slot name="heading">Perangkat ini</x-slot>
            <x-slot name="description">Push notification muncul di HP/laptop walau dashboard tidak sedang dibuka.</x-slot>

            @if (blank($vapidPublicKey))
                <p class="bem-notif__warn">Push notification belum dikonfigurasi di server (kunci VAPID kosong). Hubungi Super Admin.</p>
            @else
                <template x-if="! supported">
                    <p class="bem-notif__warn">
                        Browser ini belum mendukung push notification.
                        <span x-show="ios">Di iPhone/iPad, ikuti panduan di bawah (tambahkan ke Layar Utama lalu buka dari ikonnya).</span>
                    </p>
                </template>
                <div x-show="supported" class="bem-notif__row">
                    <span>
                        Status:
                        <strong x-text="subscribed ? 'Aktif' : (permission === 'denied' ? 'Diblokir di browser' : 'Belum aktif')"></strong>
                    </span>
                    <span class="bem-notif__actions">
                        <x-filament::button x-show="! subscribed" x-on:click="enable()" x-bind:disabled="busy" icon="heroicon-o-bell-alert">Aktifkan di perangkat ini</x-filament::button>
                        <x-filament::button x-show="subscribed" x-on:click="disable()" x-bind:disabled="busy" color="gray">Matikan di perangkat ini</x-filament::button>
                        <x-filament::button wire:click="sendTest" color="gray" icon="heroicon-o-paper-airplane">Kirim notifikasi uji</x-filament::button>
                    </span>
                </div>
                <p x-show="message" x-text="message" class="bem-notif__hint" role="status"></p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Jenis notifikasi push</x-slot>
            <x-slot name="description">Mematikan jenis di sini hanya menghentikan push ke HP/laptop. Notifikasi di lonceng dashboard tetap selalu masuk.</x-slot>
            <div class="bem-notif__list">
                @foreach ($categories as $category)
                    <label class="bem-notif__toggle" wire:key="pref-{{ $category->value }}">
                        <input type="checkbox" wire:model.live="preferences.{{ $category->value }}">
                        <span>
                            <strong>{{ $category->label() }}</strong>
                            <small>{{ $category->description() }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Perangkat terdaftar</x-slot>
            @forelse ($devices as $device)
                <div class="bem-notif__row" wire:key="dev-{{ $device['id'] }}">
                    <span><strong>{{ $device['service'] }}</strong> <small class="bem-notif__hint">· sejak {{ $device['since'] }}</small></span>
                    <x-filament::link tag="button" color="danger" size="sm" wire:click="removeDevice({{ $device['id'] }})" wire:confirm="Hapus perangkat ini dari notifikasi?">Hapus</x-filament::link>
                </div>
            @empty
                <p class="bem-notif__hint">Belum ada perangkat. Aktifkan di setiap HP/laptop yang Anda pakai.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Panduan iPhone / iPad</x-slot>
            <ol class="bem-notif__steps">
                <li>Pastikan iOS/iPadOS versi <strong>16.4 atau lebih baru</strong>.</li>
                <li>Buka dashboard di <strong>Safari</strong> (bukan Chrome/aplikasi lain).</li>
                <li>Ketuk tombol <strong>Bagikan</strong> (kotak dengan panah ke atas) → <strong>Tambahkan ke Layar Utama</strong> → <strong>Tambah</strong>.</li>
                <li>Tutup Safari, lalu buka dashboard dari <strong>ikon BEM UBHINUS</strong> di Layar Utama.</li>
                <li>Masuk ke halaman ini lagi dan ketuk <strong>Aktifkan di perangkat ini</strong> → <strong>Izinkan</strong>.</li>
                <li>Ketuk <strong>Kirim notifikasi uji</strong> untuk memastikan berhasil.</li>
            </ol>
            <p class="bem-notif__hint" x-show="ios && ! standalone">Anda sedang membuka dari Safari biasa — lakukan langkah 3–4 terlebih dahulu.</p>
        </x-filament::section>
    </div>

    <style>
        .bem-notif { display: grid; gap: 1.25rem; max-width: 52rem; font-size: .9rem; }
        .bem-notif__row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: .5rem 0; }
        .bem-notif__actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .bem-notif__hint { font-size: .8rem; color: rgb(100 116 139); }
        .bem-notif__warn { padding: .75rem 1rem; border-radius: .75rem; background: rgb(254 243 199 / .7); color: #78350f; }
        .dark .bem-notif__warn { background: rgb(120 53 15 / .3); color: #fde68a; }
        .bem-notif__list { display: grid; gap: .5rem; }
        .bem-notif__toggle { display: flex; gap: .75rem; align-items: flex-start; padding: .6rem .75rem; border-radius: .75rem; border: 1px solid rgb(148 163 184 / .35); cursor: pointer; }
        .bem-notif__toggle input { margin-top: .2rem; width: 1.1rem; height: 1.1rem; accent-color: #232361; }
        .bem-notif__toggle span { display: grid; gap: .1rem; }
        .bem-notif__toggle small { color: rgb(100 116 139); }
        .bem-notif__steps { list-style: decimal; padding-left: 1.25rem; display: grid; gap: .35rem; }
    </style>
</x-filament-panels::page>
