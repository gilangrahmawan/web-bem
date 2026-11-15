@php
    use App\Support\Format;
@endphp
<x-layouts.public :title="$post->title" :description="$post->summary(160)" :image="$post->coverUrl()" type="article">
    <x-slot:head>
        <meta property="article:published_time" content="{{ $post->published_at?->toIso8601String() }}">
        <meta property="article:modified_time" content="{{ $post->updated_at?->toIso8601String() }}">
    </x-slot:head>

    <article>
        <header class="bg-brand-primary text-white">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
                <nav class="text-sm text-white/70" aria-label="Breadcrumb">
                    <a href="{{ route($backRoute) }}" class="hover:text-brand-accent">{{ $backLabel }}</a>
                    <span aria-hidden="true"> / </span>
                    <span class="text-white">{{ $post->category?->label() ?? $post->type->label() }}</span>
                </nav>
                <h1 class="mt-4 text-3xl leading-tight font-extrabold tracking-tight text-balance sm:text-4xl lg:text-5xl">{{ $post->title }}</h1>
                <p class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/80">
                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ Format::date($post->published_at) }}</time>
                    <span>Oleh {{ $post->author?->name ?? 'BEM UBHINUS' }}</span>
                    <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-brand-accent">{{ $post->type->label() }}</span>
                </p>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            @if ($post->coverUrl())
                <img src="{{ $post->coverUrl() }}" alt="" class="-mt-20 mb-10 aspect-[16/9] w-full rounded-2xl object-cover shadow-xl sm:-mt-24" fetchpriority="high">
            @endif

            @if ($post->excerpt)
                <p class="mb-8 text-xl leading-relaxed font-medium text-slate-700">{{ $post->excerpt }}</p>
            @endif

            <div class="prose-bem">
                {!! $post->safeBody() !!}
            </div>

            @if ($post->attachmentUrl())
                <a href="{{ $post->attachmentUrl() }}" target="_blank" rel="noopener"
                    class="mt-10 flex items-center gap-4 rounded-2xl bg-brand-primary-50 p-5 ring-1 ring-brand-primary-100 transition hover:ring-brand-primary">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-primary text-brand-accent"><x-public.icon name="arrow-down-tray" class="size-6" /></span>
                    <span class="min-w-0">
                        <span class="block font-bold text-brand-primary">Unduh dokumen</span>
                        <span class="block truncate text-sm text-slate-600">{{ $post->attachment_name ?? 'Lampiran PDF' }}</span>
                    </span>
                </a>
            @endif

            <div class="mt-12 border-t border-slate-200 pt-6">
                <a href="{{ route($backRoute) }}" class="inline-flex items-center gap-2 font-semibold text-brand-primary hover:text-brand-secondary">
                    ← Kembali ke {{ $backLabel }}
                </a>
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="bg-slate-50 py-14">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-extrabold text-brand-primary">Baca juga</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-public.post-card :post="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.public>
