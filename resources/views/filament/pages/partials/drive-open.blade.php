{{-- Tag pembuka item Drive; tag penutup ditulis pemanggil memakai $item['tag'] (lihat Drive::present()). --}}
@switch($item['tag'])
    @case('button')
        @if ($item['type'] === 'folder')
            <button type="button" class="{{ $class }}" wire:click="go('folder', {{ $item['id'] }})" title="Buka folder {{ $item['name'] }}">
        @else
            <button type="button" class="{{ $class }}" title="Pratinjau {{ $item['name'] }}"
                x-on:click="preview = {{ Js::from(['name' => $item['name'], 'url' => $item['preview'], 'kind' => $item['previewKind'], 'download' => $item['download']]) }}">
        @endif
        @break
    @case('a')
        <a href="{{ $item['open'] ?? $item['download'] }}" class="{{ $class }}" title="{{ $item['type'] === 'document' ? 'Buka dokumen' : 'Unduh' }} {{ $item['name'] }}">
        @break
    @default
        <div class="{{ $class }}">
@endswitch
