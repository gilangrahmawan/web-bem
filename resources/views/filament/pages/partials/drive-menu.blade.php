{{-- Bintang + menu titik tiga. Menu yang tidak diizinkan tidak tampil; backend tetap memeriksa ulang. --}}
@php $args = Js::from(['type' => $item['type'], 'id' => $item['id']]); @endphp
<div class="bem-drive__menu">
    @unless ($item['trashed'])
        <button type="button" wire:click="toggleStar('{{ $item['type'] }}', {{ $item['id'] }})"
            @class(['bem-drive__icon-btn', 'is-starred' => $item['starred']])
            title="{{ $item['starred'] ? 'Hapus bintang' : 'Beri bintang' }}" aria-pressed="{{ $item['starred'] ? 'true' : 'false' }}">
            <x-filament::icon :icon="$item['starred'] ? 'heroicon-s-star' : 'heroicon-o-star'" class="bem-drive__nav-icon" />
        </button>
    @endunless
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <button type="button" class="bem-drive__icon-btn" title="Menu {{ $item['name'] }}" aria-label="Menu {{ $item['name'] }}">
                <x-filament::icon icon="heroicon-o-ellipsis-vertical" class="bem-drive__nav-icon" />
            </button>
        </x-slot>
        <x-filament::dropdown.list>
            @if ($item['trashed'])
                @if ($item['can']['restore'])
                    <x-filament::dropdown.list.item icon="heroicon-o-arrow-uturn-left" wire:click="mountAction('restore', {{ $args }})">Pulihkan</x-filament::dropdown.list.item>
                @endif
                @if ($item['can']['forceDelete'])
                    <x-filament::dropdown.list.item icon="heroicon-o-x-circle" color="danger" wire:click="mountAction('forceDelete', {{ $args }})">Hapus permanen</x-filament::dropdown.list.item>
                @endif
            @else
                @if ($item['type'] === 'folder')
                    <x-filament::dropdown.list.item icon="heroicon-o-folder-open" wire:click="go('folder', {{ $item['id'] }})">Buka</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="heroicon-o-arrow-down-tray" tag="a" :href="route('panel.drive.folders.zip', $item['id'])">Unduh ZIP</x-filament::dropdown.list.item>
                @elseif ($item['type'] === 'document')
                    <x-filament::dropdown.list.item icon="heroicon-o-pencil" tag="a" :href="$item['open']">Buka</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="heroicon-o-document-arrow-down" tag="a" :href="route('panel.drive.documents.export', [$item['id'], 'pdf'])">Unduh PDF</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="heroicon-o-document-arrow-down" tag="a" :href="route('panel.drive.documents.export', [$item['id'], 'docx'])">Unduh DOCX</x-filament::dropdown.list.item>
                @else
                    @if (! empty($item['preview']))
                        <x-filament::dropdown.list.item icon="heroicon-o-eye"
                            x-on:click="preview = {{ Js::from(['name' => $item['name'], 'url' => $item['preview'], 'kind' => $item['previewKind'], 'download' => $item['download']]) }}">Pratinjau</x-filament::dropdown.list.item>
                    @endif
                    <x-filament::dropdown.list.item icon="heroicon-o-arrow-down-tray" tag="a" :href="$item['download']">Unduh</x-filament::dropdown.list.item>
                @endif
                @if ($item['can']['rename'])
                    <x-filament::dropdown.list.item icon="heroicon-o-pencil-square" wire:click="mountAction('rename', {{ $args }})">Ganti nama</x-filament::dropdown.list.item>
                @endif
                @if ($item['can']['move'])
                    <x-filament::dropdown.list.item icon="heroicon-o-arrows-right-left" wire:click="mountAction('move', {{ $args }})">Pindahkan</x-filament::dropdown.list.item>
                @endif
                @if ($item['can']['share'])
                    <x-filament::dropdown.list.item icon="heroicon-o-user-plus" wire:click="mountAction('share', {{ $args }})">Bagikan</x-filament::dropdown.list.item>
                @endif
                <x-filament::dropdown.list.item :icon="$item['starred'] ? 'heroicon-s-star' : 'heroicon-o-star'" wire:click="toggleStar('{{ $item['type'] }}', {{ $item['id'] }})">
                    {{ $item['starred'] ? 'Hapus bintang' : 'Beri bintang' }}
                </x-filament::dropdown.list.item>
                @if ($item['can']['trash'])
                    <x-filament::dropdown.list.item icon="heroicon-o-trash" color="danger" wire:click="mountAction('trash', {{ $args }})">Hapus</x-filament::dropdown.list.item>
                @endif
            @endif
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
