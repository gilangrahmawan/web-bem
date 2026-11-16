<x-filament-panels::page>
    <div style="display: grid; gap: 1.25rem; max-width: 56rem;">
        @unless ($encrypted)
            <div style="padding: .8rem 1rem; border-radius: .75rem; background: rgb(254 226 226); color: #7f1d1d;">
                <strong>BACKUP_ARCHIVE_PASSWORD belum diisi.</strong> Berkas backup berisi data pribadi pengurus dan pendaftar — isi password di <code>.env</code> agar ZIP terenkripsi (lihat DEPLOY.md).
            </div>
        @endunless

        <x-filament::section>
            <x-slot name="heading">Ringkasan</x-slot>
            <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .75rem; font-size: .9rem;">
                <div><dt style="color: rgb(100 116 139);">Backup terakhir</dt><dd><strong>{{ $latest ? \App\Support\Format::date($latest['date']) : 'Belum ada' }}</strong></dd></div>
                <div><dt style="color: rgb(100 116 139);">Jumlah berkas</dt><dd><strong>{{ $files->count() }}</strong> ({{ $total }})</dd></div>
                <div><dt style="color: rgb(100 116 139);">Lokasi</dt><dd><strong>{{ $offsite ? 'Penyimpanan luar ('.$disk.')' : 'Server ini (storage/backups)' }}</strong></dd></div>
                <div><dt style="color: rgb(100 116 139);">Jadwal</dt><dd>Database tiap hari 01.00 · berkas tiap Minggu 01.30 WIB</dd></div>
            </dl>
            @unless ($offsite)
                <p style="margin-top: .75rem; font-size: .85rem; color: #92400e;">
                    Backup masih disimpan di server yang sama. Setiap awal bulan, unduh backup terbaru dan simpan di tempat lain
                    (mis. Google Drive akun BEM atau flashdisk Sekretariat). Pengingat dikirim otomatis tiap tanggal 1.
                </p>
            @endunless
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Berkas backup</x-slot>
            <x-slot name="description">ZIP terenkripsi; butuh BACKUP_ARCHIVE_PASSWORD untuk membuka. Restore database juga butuh APP_KEY yang sama.</x-slot>
            @if ($error)
                <p style="color: #b91c1c;">{{ $error }}</p>
            @endif
            @forelse ($files as $file)
                <div wire:key="bk-{{ $file['name'] }}" style="display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .55rem 0; border-bottom: 1px solid rgb(148 163 184 / .3);">
                    <span><strong>{{ $file['date'] }}</strong> <small style="color: rgb(100 116 139);">· {{ $file['size'] }} · {{ $file['name'] }}</small></span>
                    <x-filament::link :href="$file['url']" icon="heroicon-o-arrow-down-tray">Unduh</x-filament::link>
                </div>
            @empty
                <p style="color: rgb(100 116 139);">Belum ada backup. Tekan "Backup database sekarang" atau tunggu jadwal otomatis.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
