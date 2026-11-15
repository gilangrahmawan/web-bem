@php
    use App\Support\Format;
    use Illuminate\Support\Facades\Storage;

    $heroImage = ! empty($site['hero_image']) ? Storage::disk('public')->url($site['hero_image']) : null;
@endphp
<x-layouts.public :description="$site['hero_subtitle'] ?? null">
    {{-- Banner --}}
    <section class="relative isolate overflow-hidden bg-brand-primary text-white">
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" class="absolute inset-0 -z-10 size-full object-cover opacity-30" fetchpriority="high">
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-primary via-brand-primary/90 to-brand-secondary/80"></div>
        <div class="pointer-events-none absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-brand-accent/20 blur-3xl"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.4fr_1fr] lg:px-8">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide text-brand-accent ring-1 ring-white/15">
                    <x-public.icon name="sparkles" class="size-4" />
                    {{ $site['cabinet_name'] ?? 'BEM UBHINUS' }}
                </p>
                <h1 class="mt-5 text-4xl leading-tight font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                    {{ $site['hero_title'] ?? 'Badan Eksekutif Mahasiswa Universitas Bhinneka Nusantara' }}
                </h1>
                <p class="mt-5 max-w-2xl text-lg text-white/80">
                    {{ $site['hero_subtitle'] ?? 'Wadah aspirasi, karya, dan pergerakan mahasiswa UBHINUS Malang.' }}
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('public.aspirations.create') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-6 py-3.5 font-bold text-brand-primary shadow-lg shadow-black/20 transition hover:bg-yellow-300">
                        <x-public.icon name="chat" />
                        Kirim Aspirasi
                    </a>
                    <a href="{{ route('public.submissions.create') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-6 py-3.5 font-bold text-white ring-1 ring-white/30 transition hover:bg-white/20">
                        <x-public.icon name="document" />
                        Ajukan Proposal
                    </a>
                </div>
            </div>
            <div class="hidden justify-center lg:flex">
                <img src="{{ asset('images/logo-bem.webp') }}" alt="Logo BEM UBHINUS" width="320" height="400" class="h-80 w-auto drop-shadow-2xl">
            </div>
        </div>
    </section>

    {{-- Sambutan Ketua --}}
    @if (! empty($site['leader_welcome']))
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid gap-8 rounded-3xl bg-brand-primary-50 p-6 sm:p-10 lg:grid-cols-[auto_1fr] lg:gap-12">
                <div class="flex items-start">
                    <span class="flex size-16 items-center justify-center rounded-2xl bg-brand-primary text-4xl leading-none font-extrabold text-brand-accent">&ldquo;</span>
                </div>
                <div>
                    <h2 class="text-sm font-bold tracking-widest text-brand-secondary uppercase">Sambutan Ketua BEM</h2>
                    <div class="mt-4 space-y-4 text-lg leading-relaxed text-slate-700">
                        @foreach (preg_split('/\R{2,}/', trim($site['leader_welcome'])) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    </div>
                    <a href="{{ route('public.profile') }}" class="mt-6 inline-flex items-center gap-2 font-semibold text-brand-primary hover:text-brand-secondary">
                        Kenali kami lebih jauh <x-public.icon name="arrow-right" class="size-4" />
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Berita terbaru --}}
    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-bold tracking-widest text-brand-secondary uppercase">Kabar Terbaru</p>
                <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-brand-primary">Berita</h2>
            </div>
            <a href="{{ route('public.news.index') }}" class="hidden items-center gap-1 font-semibold text-brand-primary hover:text-brand-secondary sm:inline-flex">
                Semua berita <x-public.icon name="arrow-right" class="size-4" />
            </a>
        </div>

        @if ($latestNews->isNotEmpty())
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latestNews as $post)
                    <x-public.post-card :post="$post" />
                @endforeach
            </div>
        @else
            <x-public.empty-state class="mt-8" title="Belum ada berita" message="Berita kegiatan BEM akan tampil di sini." />
        @endif

        <a href="{{ route('public.news.index') }}" class="mt-6 inline-flex items-center gap-1 font-semibold text-brand-primary sm:hidden">
            Semua berita <x-public.icon name="arrow-right" class="size-4" />
        </a>
    </section>

    {{-- Agenda terdekat + Kajian terbaru --}}
    <section class="bg-slate-50 py-16">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-accent text-brand-primary"><x-public.icon name="calendar" class="size-6" /></span>
                        <h2 class="text-xl font-extrabold text-brand-primary">Agenda Terdekat</h2>
                    </div>
                    <a href="{{ route('public.calendar') }}" class="text-sm font-semibold text-brand-primary hover:text-brand-secondary">Kalender</a>
                </div>
                @if ($upcomingEvents->isNotEmpty())
                    <ul class="mt-6 divide-y divide-slate-100">
                        @foreach ($upcomingEvents as $event)
                            @include('public.partials.event-item', ['event' => $event])
                        @endforeach
                    </ul>
                @else
                    <x-public.empty-state class="mt-6" icon="clock" title="Belum ada agenda terdekat"
                        message="Agenda BEM, kampus, dan UKM akan tampil di sini." />
                @endif
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-brand-primary text-brand-accent"><x-public.icon name="document" class="size-6" /></span>
                        <h2 class="text-xl font-extrabold text-brand-primary">Kajian & Pernyataan Sikap</h2>
                    </div>
                    <a href="{{ route('public.studies.index') }}" class="text-sm font-semibold text-brand-primary hover:text-brand-secondary">Semua</a>
                </div>
                @if ($latestStudies->isNotEmpty())
                    <ul class="mt-6 divide-y divide-slate-100">
                        @foreach ($latestStudies as $study)
                            <li class="py-4 first:pt-0 last:pb-0">
                                <a href="{{ $study->publicUrl() }}" class="group block">
                                    <span class="text-xs font-semibold text-brand-secondary">{{ $study->type->label() }} · {{ Format::date($study->published_at) }}</span>
                                    <span class="mt-1 block font-bold text-slate-900 group-hover:text-brand-primary">{{ $study->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-public.empty-state class="mt-6" title="Belum ada kajian" />
                @endif
            </div>
        </div>
    </section>

    {{-- Galeri --}}
    @if ($albums->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <h2 class="text-3xl font-extrabold tracking-tight text-brand-primary">Galeri Kegiatan</h2>
                <a href="{{ route('public.galleries.index') }}" class="inline-flex items-center gap-1 font-semibold text-brand-primary hover:text-brand-secondary">
                    Lihat galeri <x-public.icon name="arrow-right" class="size-4" />
                </a>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($albums as $album)
                    <a href="{{ route('public.galleries.show', $album->slug) }}" class="group relative block aspect-square overflow-hidden rounded-2xl bg-brand-primary-100">
                        @if ($album->coverPhoto)
                            <img src="{{ $album->coverPhoto->thumbUrl() }}" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
                        @endif
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-3 text-sm font-semibold text-white">{{ $album->title }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.public>
