@php
    use App\Enums\PostCategory;
@endphp
<x-layouts.public title="Berita" description="Berita dan kabar kegiatan terbaru BEM Universitas Bhinneka Nusantara.">
    <x-public.page-header eyebrow="Kabar BEM" title="Berita" subtitle="Kegiatan, pengumuman, prestasi, dan opini dari BEM UBHINUS." />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" aria-label="Filter kategori">
                @include('public.partials.filter-pill', [
                    'href' => route('public.news.index', array_filter(['q' => $search])),
                    'label' => 'Semua',
                    'active' => $activeCategory === null,
                ])
                @foreach (PostCategory::cases() as $category)
                    @include('public.partials.filter-pill', [
                        'href' => route('public.news.index', array_filter(['kategori' => $category->slug(), 'q' => $search])),
                        'label' => $category->label(),
                        'active' => $activeCategory === $category,
                    ])
                @endforeach
            </nav>
            @include('public.partials.search-form', [
                'action' => route('public.news.index'),
                'search' => $search,
                'placeholder' => 'Cari berita…',
                'hidden' => ['kategori' => $activeCategory?->slug()],
            ])
        </div>

        @if ($search !== '')
            <p class="mt-6 text-sm text-slate-600">Hasil pencarian untuk <strong>“{{ $search }}”</strong>: {{ $posts->total() }} berita</p>
        @endif

        @if ($posts->isNotEmpty())
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-public.post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @else
            <x-public.empty-state class="mt-8" icon="magnifying-glass" title="Berita tidak ditemukan"
                :message="$search !== '' ? 'Coba kata kunci lain.' : 'Belum ada berita pada kategori ini.'" />
        @endif
    </div>
</x-layouts.public>
