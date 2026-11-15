{{-- Form cari (GET). Variabel: $action, $search, $placeholder, $hidden (array nama => nilai yang dipertahankan) --}}
<form action="{{ $action }}" method="GET" role="search" class="relative w-full sm:max-w-sm">
    @foreach (array_filter($hidden ?? []) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <label for="q" class="sr-only">Cari</label>
    <x-public.icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400" />
    <input id="q" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ $placeholder ?? 'Cari…' }}"
        class="w-full rounded-xl border-0 bg-white py-3 pr-4 pl-11 text-sm shadow-sm ring-1 ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-primary">
</form>
