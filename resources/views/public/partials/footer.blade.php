@php
    $socials = array_filter([
        'instagram' => $site['social_instagram'] ?? null,
        'tiktok' => $site['social_tiktok'] ?? null,
        'youtube' => $site['social_youtube'] ?? null,
    ]);
@endphp
<footer class="mt-auto bg-brand-primary-900 text-white/80">
    <div class="h-1 bg-gradient-to-r from-brand-accent via-brand-accent to-brand-secondary"></div>
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
        <div class="lg:col-span-2">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo-bem.webp') }}" alt="Logo BEM UBHINUS" width="56" height="70" class="h-14 w-auto" loading="lazy">
                <img src="{{ asset('images/logo-ubhinus.webp') }}" alt="Logo Universitas Bhinneka Nusantara" width="56" height="56" class="h-14 w-auto" loading="lazy">
            </div>
            <p class="mt-5 text-lg font-bold text-white">Badan Eksekutif Mahasiswa</p>
            <p class="text-sm">Universitas Bhinneka Nusantara, Malang</p>
            @if (! empty($site['cabinet_name']))
                <p class="mt-1 text-sm text-brand-accent">{{ $site['cabinet_name'] }}</p>
            @endif

            @if ($socials)
                <div class="mt-6 flex gap-3">
                    @foreach ($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-brand-accent hover:text-brand-primary">
                            <span class="sr-only">{{ ucfirst($network) }}</span>
                            <x-public.icon :name="$network" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-sm font-bold tracking-wider text-white uppercase">Jelajahi</h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a class="hover:text-brand-accent" href="{{ route('public.profile') }}">Profil</a></li>
                <li><a class="hover:text-brand-accent" href="{{ route('public.structure') }}">Struktur</a></li>
                <li><a class="hover:text-brand-accent" href="{{ route('public.programs.index') }}">Program Kerja</a></li>
                <li><a class="hover:text-brand-accent" href="{{ route('public.news.index') }}">Berita</a></li>
                <li><a class="hover:text-brand-accent" href="{{ route('public.galleries.index') }}">Galeri</a></li>
                <li><a class="hover:text-brand-accent" href="{{ route('public.downloads.index') }}">Unduhan</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-bold tracking-wider text-white uppercase">Hubungi Kami</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @if (! empty($site['contact_address']))
                    <li class="flex gap-2"><x-public.icon name="map-pin" class="mt-0.5 size-5 shrink-0 text-brand-accent" /><span>{{ $site['contact_address'] }}</span></li>
                @endif
                @if (! empty($site['contact_email']))
                    <li class="flex gap-2"><x-public.icon name="envelope" class="mt-0.5 size-5 shrink-0 text-brand-accent" /><a class="hover:text-brand-accent" href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a></li>
                @endif
                @if (! empty($site['contact_phone']))
                    <li class="flex gap-2"><x-public.icon name="phone" class="mt-0.5 size-5 shrink-0 text-brand-accent" /><span>{{ $site['contact_phone'] }}</span></li>
                @endif
                <li><a class="font-semibold text-brand-accent hover:underline" href="{{ route('public.aspirations.create') }}">Kirim aspirasi →</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs text-white/50 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <p>&copy; {{ now()->year }} BEM Universitas Bhinneka Nusantara. Hak cipta dilindungi.</p>
            <a href="{{ url('/panel-bem') }}" class="hover:text-white" rel="nofollow">Masuk pengurus</a>
        </div>
    </div>
</footer>
