<x-layouts.public :title="$title" noindex>
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6">
        <span class="flex size-16 items-center justify-center rounded-2xl bg-brand-accent text-brand-primary">
            <x-public.icon name="clock" class="size-8" />
        </span>
        <p class="mt-6 text-sm font-bold tracking-widest text-brand-secondary uppercase">Segera hadir</p>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-brand-primary sm:text-4xl">{{ $title }}</h1>
        <p class="mt-4 text-slate-600">Layanan ini sedang kami siapkan dan akan segera bisa digunakan. Sementara itu, hubungi BEM melalui halaman kontak.</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('public.home') }}" class="rounded-xl bg-brand-primary px-6 py-3 font-bold text-white hover:bg-brand-secondary">Ke beranda</a>
            <a href="{{ route('public.contact') }}" class="rounded-xl px-6 py-3 font-bold text-brand-primary ring-1 ring-brand-primary-200 hover:bg-brand-primary-50">Kontak BEM</a>
        </div>
    </section>
</x-layouts.public>
