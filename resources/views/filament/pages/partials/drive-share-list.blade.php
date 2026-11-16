{{-- Daftar akses folder di modal "Bagikan". Cabut akses lewat Drive::unshare() (diperiksa ulang di DriveService). --}}
<div style="display: grid; gap: .5rem;">
    <p style="font-weight: 600;">Akses saat ini</p>
    @forelse ($permissions as $permission)
        <div wire:key="perm-{{ $permission->id }}" style="display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .5rem .75rem; border: 1px solid rgb(148 163 184 / .35); border-radius: .6rem;">
            <span>{{ $permission->granteeLabel() }}</span>
            <span style="display: flex; align-items: center; gap: .5rem;">
                <x-filament::badge :color="$permission->access->color()">{{ $permission->access->label() }}</x-filament::badge>
                <x-filament::link tag="button" color="danger" size="sm" wire:click="unshare({{ $permission->id }})" wire:confirm="Cabut akses ini?">Cabut</x-filament::link>
            </span>
        </div>
    @empty
        <p style="font-size: .875rem; color: rgb(100 116 139);">Belum dibagikan. Super Admin & Sekretaris selalu bisa mengelola semua folder; Pembina bisa melihat.</p>
    @endforelse
</div>
