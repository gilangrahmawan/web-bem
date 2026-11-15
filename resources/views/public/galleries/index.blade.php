@php
    use App\Support\Format;
@endphp
<x-layouts.public title="Galeri" description="Dokumentasi foto kegiatan BEM Universitas Bhinneka Nusantara.">
    <x-public.page-header eyebrow="Dokumentasi" title="Galeri Kegiatan" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($albums->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $album)
                    <a href="{{ route('public.galleries.show', $album->slug) }}" class="group block overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg">
                        <div class="aspect-[4/3] overflow-hidden bg-brand-primary-100">
                            @if ($album->coverPhoto)
                                <img src="{{ $album->coverPhoto->thumbUrl() }}" alt="" loading="lazy" decoding="async" class="size-full object-cover transition duration-500 group-hover:scale-105">
                            @endif
                        </div>
                        <div class="p-5">
                            <h2 class="font-bold text-slate-900 group-hover:text-brand-primary">{{ $album->title }}</h2>
                            <p class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                                @if ($album->event_date)
                                    <span>{{ Format::date($album->event_date) }}</span>
                                @endif
                                <span class="inline-flex items-center gap-1"><x-public.icon name="photo" class="size-4" /> {{ $album->photos_count }} foto</span>
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $albums->links() }}</div>
        @else
            <x-public.empty-state icon="photo" title="Belum ada album" />
        @endif
    </div>
</x-layouts.public>
