@php
    $mapUrl = (string) ($site['maps_embed_url'] ?? '');
    // Hanya embed Google Maps resmi yang diizinkan (juga dibatasi CSP frame-src).
    $mapAllowed = str_starts_with($mapUrl, 'https://www.google.com/maps/embed');
    $socials = array_filter([
        'instagram' => $site['social_instagram'] ?? null,
        'tiktok' => $site['social_tiktok'] ?? null,
        'youtube' => $site['social_youtube'] ?? null,
    ]);
@endphp
<x-layouts.public title="Kontak" description="Alamat sekretariat, email, dan media sosial BEM Universitas Bhinneka Nusantara.">
    <x-public.page-header eyebrow="Kontak" title="Hubungi BEM UBHINUS" subtitle="Sampaikan pertanyaan, kerja sama, atau undangan kepada kami." />

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_1.3fr] lg:px-8">
        <div class="space-y-4">
            @if (! empty($site['contact_address']))
                <div class="flex gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-primary text-brand-accent"><x-public.icon name="map-pin" /></span>
                    <div><p class="text-sm font-bold text-slate-900">Sekretariat</p><p class="mt-1 text-slate-600">{!! nl2br(e($site['contact_address'])) !!}</p></div>
                </div>
            @endif
            @if (! empty($site['contact_email']))
                <div class="flex gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-primary text-brand-accent"><x-public.icon name="envelope" /></span>
                    <div><p class="text-sm font-bold text-slate-900">Email</p><a href="mailto:{{ $site['contact_email'] }}" class="mt-1 block text-brand-secondary hover:underline">{{ $site['contact_email'] }}</a></div>
                </div>
            @endif
            @if (! empty($site['contact_phone']))
                <div class="flex gap-4 rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-primary text-brand-accent"><x-public.icon name="phone" /></span>
                    <div><p class="text-sm font-bold text-slate-900">Telepon / WhatsApp</p><p class="mt-1 text-slate-600">{{ $site['contact_phone'] }}</p></div>
                </div>
            @endif
            @if ($socials)
                <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                    <p class="text-sm font-bold text-slate-900">Media sosial</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($socials as $network => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-50 px-4 py-2 text-sm font-semibold text-brand-primary hover:bg-brand-primary hover:text-white">
                                <x-public.icon :name="$network" class="size-4" /> {{ ucfirst($network) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            <a href="{{ route('public.aspirations.create') }}" class="flex items-center justify-between rounded-2xl bg-brand-accent p-5 font-bold text-brand-primary">
                Punya aspirasi untuk BEM? Kirim di sini <x-public.icon name="arrow-right" />
            </a>
        </div>

        <div class="min-h-80 overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200">
            @if ($mapAllowed)
                <iframe src="{{ $mapUrl }}" title="Peta lokasi sekretariat BEM UBHINUS" class="size-full min-h-80" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            @else
                <div class="flex size-full min-h-80 items-center justify-center p-6 text-center text-sm text-slate-500">Peta lokasi belum diatur.</div>
            @endif
        </div>
    </div>
</x-layouts.public>
