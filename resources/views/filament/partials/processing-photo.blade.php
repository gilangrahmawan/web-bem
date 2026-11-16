{{-- Indikator saat server memproses foto (GD → WebP) setelah tombol simpan ditekan. --}}
<div wire:loading.flex wire:target="{{ $target }}" role="status" aria-live="polite"
    style="align-items: center; gap: .5rem; font-size: .875rem; color: rgb(100 116 139);">
    <x-filament::loading-indicator class="h-4 w-4" />
    <span>Sedang memproses foto… mohon tunggu sebentar.</span>
</div>
