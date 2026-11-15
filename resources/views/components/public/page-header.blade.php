@props(['title', 'eyebrow' => null, 'subtitle' => null])
<section class="relative overflow-hidden bg-brand-primary text-white">
    <div class="pointer-events-none absolute inset-0 opacity-20"
        style="background-image: radial-gradient(circle at 85% 20%, #ffdb1a 0, transparent 35%), radial-gradient(circle at 10% 90%, #000078 0, transparent 40%);"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        @if ($eyebrow)
            <p class="text-sm font-bold tracking-widest text-brand-accent uppercase">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-2 max-w-3xl text-3xl font-extrabold tracking-tight text-balance sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-4 max-w-2xl text-base text-white/80 sm:text-lg">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
