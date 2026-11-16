{{-- Riwayat versi dokumen (maks. 1 versi per menit per user). Pulihkan lewat DocumentEditor::restoreVersion(). --}}
<div style="display: grid; gap: .5rem;">
    @forelse ($versions as $version)
        <div wire:key="ver-{{ $version->id }}" style="display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .55rem .8rem; border: 1px solid rgb(148 163 184 / .35); border-radius: .6rem;">
            <span>
                <strong>{{ \App\Support\Format::date($version->created_at) }}, {{ $version->created_at->format('H.i') }}</strong>
                <span style="color: rgb(100 116 139);"> · {{ $version->author?->name ?? 'Sistem' }} · {{ \Illuminate\Support\Str::limit(strip_tags((string) $version->content), 70) }}</span>
            </span>
            @if ($canRestore)
                <x-filament::link tag="button" size="sm" wire:click="restoreVersion({{ $version->id }})" wire:confirm="Pulihkan dokumen ke versi ini? Isi saat ini tetap tersimpan sebagai versi.">Pulihkan</x-filament::link>
            @endif
        </div>
    @empty
        <p style="color: rgb(100 116 139);">Belum ada riwayat versi. Versi tercatat otomatis saat dokumen diubah.</p>
    @endforelse
</div>
