<x-layouts.public title="Kajian & Pernyataan Sikap" description="Press release dan kajian publik BEM Universitas Bhinneka Nusantara.">
    <x-public.page-header eyebrow="Kajian" title="Kajian & Pernyataan Sikap"
        subtitle="Press release dan hasil kajian BEM UBHINUS atas isu kampus, daerah, dan nasional." />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Filter jenis">
                @include('public.partials.filter-pill', [
                    'href' => route('public.studies.index', array_filter(['q' => $search])),
                    'label' => 'Semua',
                    'active' => ! isset($typeFilters[$activeType]),
                ])
                @foreach ($typeFilters as $slug => $type)
                    @include('public.partials.filter-pill', [
                        'href' => route('public.studies.index', array_filter(['jenis' => $slug, 'q' => $search])),
                        'label' => $type->label(),
                        'active' => $activeType === $slug,
                    ])
                @endforeach
            </nav>
            @include('public.partials.search-form', [
                'action' => route('public.studies.index'),
                'search' => $search,
                'placeholder' => 'Cari kajian…',
                'hidden' => ['jenis' => isset($typeFilters[$activeType]) ? $activeType : null],
            ])
        </div>

        @if ($posts->isNotEmpty())
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-public.post-card :post="$post" />
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @else
            <x-public.empty-state class="mt-8" title="Belum ada kajian atau pernyataan sikap" />
        @endif
    </div>
</x-layouts.public>
