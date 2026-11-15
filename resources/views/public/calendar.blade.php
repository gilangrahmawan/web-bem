<x-layouts.public title="Kalender Kegiatan" description="Kalender kegiatan BEM UBHINUS: hari besar nasional, agenda akademik kampus, kegiatan BEM, dan kegiatan UKM.">
    <x-slot:head>
        @vite('resources/js/public-calendar.js')
    </x-slot:head>

    <x-public.page-header eyebrow="Agenda" title="Kalender Kegiatan"
        subtitle="Hari besar, agenda akademik kampus, kegiatan BEM, dan kegiatan UKM dalam satu kalender." />

    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_20rem] lg:px-8">
        <section aria-label="Kalender">
            <div class="text-slate-700">
                <x-calendar-legend :categories="$categories" toggle-attribute="data-category-toggle" />
            </div>

            <div class="bem-fc mt-6 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200 sm:p-5"
                data-public-calendar data-feed-url="{{ route('public.calendar.feed') }}">
                <noscript>Aktifkan JavaScript untuk melihat kalender. Daftar agenda terdekat tersedia di samping.</noscript>
            </div>
        </section>

        <aside class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-200 lg:self-start">
            <h2 class="flex items-center gap-2 text-lg font-extrabold text-brand-primary">
                <x-public.icon name="calendar" class="size-5" /> Agenda Terdekat
            </h2>
            @if ($upcoming->isNotEmpty())
                <ul class="mt-5 divide-y divide-slate-200">
                    @foreach ($upcoming as $event)
                        @include('public.partials.event-item', ['event' => $event])
                    @endforeach
                </ul>
            @else
                <x-public.empty-state class="mt-5" icon="clock" title="Belum ada agenda" message="Agenda mendatang akan tampil di sini." />
            @endif
        </aside>
    </div>

    <dialog data-event-dialog class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/50">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold tracking-widest text-brand-secondary uppercase" data-field="category"></p>
                    <h2 class="mt-1 text-xl font-extrabold text-brand-primary" data-field="title"></h2>
                </div>
                <form method="dialog">
                    <button class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Tutup">
                        <x-public.icon name="x" class="size-5" />
                    </button>
                </form>
            </div>

            <p data-field="conflict" class="mt-3 hidden rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                ⚠ Ada kegiatan BEM/UKM lain di tanggal yang sama.
            </p>

            <dl class="mt-4 space-y-3 text-sm">
                <div data-row class="flex gap-3">
                    <dt><x-public.icon name="clock" class="size-5 text-brand-secondary" /><span class="sr-only">Waktu</span></dt>
                    <dd data-field="when" class="text-slate-700"></dd>
                </div>
                <div data-row class="flex gap-3">
                    <dt><x-public.icon name="map-pin" class="size-5 text-brand-secondary" /><span class="sr-only">Lokasi</span></dt>
                    <dd data-field="location" class="text-slate-700"></dd>
                </div>
                <div data-row class="flex gap-3">
                    <dt><x-public.icon name="users" class="size-5 text-brand-secondary" /><span class="sr-only">Penyelenggara</span></dt>
                    <dd data-field="organizer" class="text-slate-700"></dd>
                </div>
                <div data-row>
                    <dt class="sr-only">Keterangan</dt>
                    <dd data-field="description" class="whitespace-pre-line text-slate-600"></dd>
                </div>
            </dl>

            <a data-field="registration" href="#" target="_blank" rel="noopener noreferrer"
                class="mt-6 hidden w-full rounded-xl bg-brand-primary px-5 py-3 text-center font-bold text-white hover:bg-brand-secondary">
                Daftar sekarang
            </a>
        </div>
    </dialog>
</x-layouts.public>
