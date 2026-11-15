@php
    use App\Support\Format;

    $missions = collect(preg_split('/\R/', (string) ($site['mission'] ?? '')))->map(fn ($m) => trim($m))->filter();
@endphp
<x-layouts.public title="Profil" :description="$site['vision'] ?? null">
    <x-public.page-header eyebrow="Profil" :title="$site['cabinet_name'] ?? 'BEM UBHINUS'"
        :subtitle="$period ? 'Periode '.Format::date($period->starts_on).' – '.Format::date($period->ends_on) : 'Badan Eksekutif Mahasiswa Universitas Bhinneka Nusantara'" />

    <div class="mx-auto max-w-7xl space-y-16 px-4 py-16 sm:px-6 lg:px-8">
        <section class="grid gap-8 lg:grid-cols-2">
            <div class="rounded-3xl bg-brand-primary p-8 text-white">
                <h2 class="text-sm font-bold tracking-widest text-brand-accent uppercase">Visi</h2>
                <p class="mt-4 text-xl leading-relaxed font-semibold sm:text-2xl">{{ $site['vision'] ?? '-' }}</p>
            </div>
            <div class="rounded-3xl bg-brand-primary-50 p-8">
                <h2 class="text-sm font-bold tracking-widest text-brand-secondary uppercase">Misi</h2>
                @if ($missions->isNotEmpty())
                    <ol class="mt-4 space-y-3">
                        @foreach ($missions as $mission)
                            <li class="flex gap-3">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-accent text-sm font-bold text-brand-primary">{{ $loop->iteration }}</span>
                                <span class="pt-0.5 text-slate-700">{{ $mission }}</span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="mt-4 text-slate-500">Misi belum diisi.</p>
                @endif
            </div>
        </section>

        @if (! empty($site['history']))
            <section class="max-w-3xl">
                <h2 class="text-3xl font-extrabold tracking-tight text-brand-primary">Sejarah</h2>
                <div class="prose-bem mt-6">
                    @foreach (preg_split('/\R{2,}/', trim($site['history'])) as $paragraph)
                        <p>{!! nl2br(e($paragraph)) !!}</p>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="grid items-center gap-10 rounded-3xl bg-white p-8 ring-1 ring-slate-200 lg:grid-cols-[auto_1fr]">
            <img src="{{ asset('images/logo-bem.webp') }}" alt="Logo BEM UBHINUS" width="200" height="250" class="mx-auto h-56 w-auto" loading="lazy">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-brand-primary">Logo & Filosofi</h2>
                <div class="prose-bem mt-4">
                    @if (! empty($site['logo_philosophy']))
                        @foreach (preg_split('/\R{2,}/', trim($site['logo_philosophy'])) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    @else
                        <p class="text-slate-500">Filosofi logo belum diisi.</p>
                    @endif
                </div>
            </div>
        </section>

        @if ($divisions->isNotEmpty())
            <section>
                <h2 class="text-3xl font-extrabold tracking-tight text-brand-primary">Divisi</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($divisions as $division)
                        <a href="{{ route('public.divisions.show', $division->slug) }}" class="group rounded-2xl bg-white p-6 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg">
                            <span class="block h-1.5 w-12 rounded-full" style="background-color: {{ preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $division->color) ? $division->color : '#232361' }}"></span>
                            <h3 class="mt-4 text-lg font-bold text-slate-900 group-hover:text-brand-primary">{{ $division->name }}</h3>
                            <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $division->description }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
