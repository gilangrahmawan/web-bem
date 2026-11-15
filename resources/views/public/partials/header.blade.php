@php
    use App\Support\PublicNavigation;

    $nav = PublicNavigation::items();
@endphp
<header class="sticky top-0 z-40 border-b border-white/10 bg-brand-primary/95 text-white backdrop-blur supports-[backdrop-filter]:bg-brand-primary/85">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">
        <a href="{{ route('public.home') }}" class="flex min-w-0 items-center gap-3" aria-label="BEM UBHINUS — Beranda">
            <img src="{{ asset('images/logo-bem.webp') }}" alt="" width="40" height="50" class="h-10 w-auto shrink-0 lg:h-12">
            <span class="min-w-0 leading-tight">
                <span class="block text-base font-extrabold tracking-tight lg:text-lg">BEM UBHINUS</span>
                <span class="block truncate text-xs text-white/70">{{ $site['cabinet_name'] ?? 'Universitas Bhinneka Nusantara' }}</span>
            </span>
        </a>

        {{-- Navigasi desktop --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
            @foreach ($nav as $item)
                @if (isset($item['children']))
                    <details data-dropdown class="group relative">
                        <summary @class([
                            'flex cursor-pointer list-none items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold transition hover:bg-white/10 [&::-webkit-details-marker]:hidden',
                            'text-brand-accent' => PublicNavigation::isActive($item['active'] ?? null),
                        ])>
                            {{ $item['label'] }}
                            <x-public.icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                        </summary>
                        <div class="absolute right-0 mt-2 w-72 overflow-hidden rounded-2xl bg-white p-2 text-slate-800 shadow-xl ring-1 ring-black/5">
                            @foreach ($item['children'] as $child)
                                <a href="{{ route($child['route']) }}" class="block rounded-xl px-4 py-3 hover:bg-brand-primary-50">
                                    <span class="block text-sm font-semibold text-brand-primary">{{ $child['label'] }}</span>
                                    <span class="block text-xs text-slate-500">{{ $child['description'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a href="{{ route($item['route']) }}" @class([
                        'rounded-lg px-3 py-2 text-sm font-semibold transition hover:bg-white/10',
                        'text-brand-accent' => PublicNavigation::isActive($item['active'] ?? null),
                    ]) @if (PublicNavigation::isActive($item['active'] ?? null)) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Tombol menu mobile --}}
        <button type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile"
            class="inline-flex size-11 items-center justify-center rounded-xl hover:bg-white/10 lg:hidden">
            <span class="sr-only">Buka menu</span>
            <x-public.icon name="menu" class="size-6" />
        </button>
    </div>

    {{-- Menu mobile --}}
    <div id="menu-mobile" data-menu-panel class="hidden max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-white/10 bg-brand-primary lg:hidden">
        <nav class="space-y-1 px-4 py-4" aria-label="Navigasi utama (mobile)">
            @foreach ($nav as $item)
                @if (isset($item['children']))
                    <p class="px-3 pt-4 pb-1 text-xs font-bold tracking-wider text-white/50 uppercase">{{ $item['label'] }}</p>
                    @foreach ($item['children'] as $child)
                        <a href="{{ route($child['route']) }}" class="block rounded-xl px-3 py-3 font-semibold hover:bg-white/10">
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ route($item['route']) }}" @class([
                        'block rounded-xl px-3 py-3 font-semibold hover:bg-white/10',
                        'bg-white/10 text-brand-accent' => PublicNavigation::isActive($item['active'] ?? null),
                    ])>
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
</header>
