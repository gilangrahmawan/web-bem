@php
    use App\Enums\DownloadCategory;
@endphp
<x-layouts.public title="Unduhan" description="Template proposal, template LPJ, formulir peminjaman barang, SOP, dan dokumen BEM UBHINUS.">
    <x-public.page-header eyebrow="Pusat Dokumen" title="Unduhan"
        subtitle="Template proposal & LPJ, formulir peminjaman barang, SOP, dan surat." />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4">
            @include('public.partials.search-form', [
                'action' => route('public.downloads.index'),
                'search' => $search,
                'placeholder' => 'Cari dokumen…',
                'hidden' => ['kategori' => $activeCategory?->slug()],
            ])
            <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" aria-label="Filter kategori">
                @include('public.partials.filter-pill', [
                    'href' => route('public.downloads.index', array_filter(['q' => $search])),
                    'label' => 'Semua',
                    'active' => $activeCategory === null,
                ])
                @foreach (DownloadCategory::cases() as $category)
                    @include('public.partials.filter-pill', [
                        'href' => route('public.downloads.index', array_filter(['kategori' => $category->slug(), 'q' => $search])),
                        'label' => $category->label(),
                        'active' => $activeCategory === $category,
                    ])
                @endforeach
            </nav>
        </div>

        @if ($downloads->isNotEmpty())
            <ul class="mt-8 divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200">
                @foreach ($downloads as $download)
                    <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-primary-50 text-xs font-extrabold text-brand-primary">
                            {{ $download->extension() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-900">{{ $download->title }}</p>
                            <p class="mt-0.5 text-xs font-semibold text-brand-secondary">{{ $download->category->label() }}</p>
                            @if ($download->description)
                                <p class="mt-1 text-sm text-slate-600">{{ $download->description }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-400">{{ $download->humanSize() }} · diunduh {{ number_format($download->download_count, 0, ',', '.') }} kali</p>
                        </div>
                        <a href="{{ route('public.downloads.file', $download) }}" rel="nofollow"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-bold text-white transition hover:bg-brand-secondary">
                            <x-public.icon name="arrow-down-tray" class="size-4" /> Unduh
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-10">{{ $downloads->links() }}</div>
        @else
            <x-public.empty-state class="mt-8" title="Dokumen tidak ditemukan" />
        @endif
    </div>
</x-layouts.public>
