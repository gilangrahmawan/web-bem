@props(['icon' => 'document', 'title' => 'Belum ada data', 'message' => null])
<div {{ $attributes->merge(['class' => 'rounded-2xl border-2 border-dashed border-slate-200 px-6 py-14 text-center']) }}>
    <x-public.icon :name="$icon" class="mx-auto size-10 text-brand-muted" />
    <p class="mt-4 font-semibold text-slate-800">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 text-sm text-slate-500">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
