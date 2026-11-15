@php
    use App\Support\Format;
@endphp
<x-layouts.public :title="$gallery->title" :description="$gallery->description" :image="$gallery->photos->first()?->url()">
    <x-public.page-header eyebrow="Galeri" :title="$gallery->title"
        :subtitle="collect([$gallery->event_date ? Format::date($gallery->event_date) : null, $gallery->photos->count().' foto'])->filter()->join(' · ')">
        @if ($gallery->description)
            <p class="mt-4 max-w-2xl text-white/80">{{ $gallery->description }}</p>
        @endif
    </x-public.page-header>

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="columns-1 gap-4 sm:columns-2 lg:columns-3">
            @foreach ($gallery->photos as $photo)
                <figure class="mb-4 break-inside-avoid overflow-hidden rounded-2xl bg-slate-100">
                    <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="block">
                        <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?? $gallery->title }}" loading="lazy" decoding="async" class="w-full transition hover:opacity-90">
                    </a>
                    @if ($photo->caption)
                        <figcaption class="px-4 py-3 text-sm text-slate-600">{{ $photo->caption }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
        <a href="{{ route('public.galleries.index') }}" class="mt-8 inline-flex font-semibold text-brand-primary hover:text-brand-secondary">← Semua album</a>
    </div>
</x-layouts.public>
