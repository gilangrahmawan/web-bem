{{-- Tombol filter. Variabel: $href, $label, $active --}}
<a href="{{ $href }}" @class([
    'shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition',
    'bg-brand-primary text-white shadow' => $active,
    'bg-white text-slate-700 ring-1 ring-slate-200 hover:ring-brand-primary' => ! $active,
]) @if ($active) aria-current="true" @endif>{{ $label }}</a>
