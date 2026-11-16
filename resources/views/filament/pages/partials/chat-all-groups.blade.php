{{-- Super Admin: grup buatan pengguna — METADATA saja (tanpa isi pesan). --}}
<div style="display: grid; gap: .5rem;">
    @forelse ($groups as $group)
        <div wire:key="grp-{{ $group->id }}" style="display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .55rem .8rem; border: 1px solid rgb(148 163 184 / .35); border-radius: .6rem;">
            <span>
                <strong>{{ $group->name }}</strong>
                <span style="color: rgb(100 116 139); font-size: .85rem;"> · {{ $group->members_count }} anggota · dibuat {{ $group->creator?->name ?? '-' }}</span>
            </span>
            <x-filament::link tag="button" color="danger" size="sm" wire:click="dissolveAny({{ $group->id }})"
                wire:confirm="Bubarkan grup {{ $group->name }}? Seluruh pesan & lampirannya dihapus permanen.">Bubarkan</x-filament::link>
        </div>
    @empty
        <p style="color: rgb(100 116 139);">Belum ada grup buatan pengguna.</p>
    @endforelse
</div>
