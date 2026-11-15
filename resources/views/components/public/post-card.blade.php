@props(['post'])
@php
    use App\Support\Format;
@endphp
<article class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg">
    <a href="{{ $post->publicUrl() }}" class="relative block aspect-[16/10] overflow-hidden bg-brand-primary-100" tabindex="-1" aria-hidden="true">
        @if ($post->coverUrl())
            <img src="{{ $post->coverUrl() }}" alt="" loading="lazy" decoding="async"
                class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex size-full items-center justify-center bg-gradient-to-br from-brand-primary to-brand-secondary">
                <img src="{{ asset('images/logo-bem.webp') }}" alt="" class="h-16 w-auto opacity-40" loading="lazy">
            </div>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
            <span class="rounded-full bg-brand-primary-50 px-2.5 py-1 text-brand-primary">
                {{ $post->category?->label() ?? $post->type?->label() }}
            </span>
            <time datetime="{{ $post->published_at?->toDateString() }}" class="text-slate-500">{{ Format::date($post->published_at) }}</time>
        </div>
        <h3 class="mt-3 text-lg leading-snug font-bold text-slate-900 group-hover:text-brand-primary">
            <a href="{{ $post->publicUrl() }}" class="after:absolute after:inset-0 focus:outline-none">{{ $post->title }}</a>
        </h3>
        <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $post->summary() }}</p>
    </div>
</article>
