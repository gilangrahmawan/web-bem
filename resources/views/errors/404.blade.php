<x-layouts.public title="Halaman tidak ditemukan" noindex>
    <section class="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center sm:px-6">
        <p class="text-7xl font-extrabold text-brand-accent drop-shadow-sm">404</p>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-brand-primary">Halaman tidak ditemukan</h1>
        <p class="mt-4 text-slate-600">Halaman yang Anda cari tidak ada, sudah dipindahkan, atau belum dipublikasikan.</p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('public.home') }}" class="rounded-xl bg-brand-primary px-6 py-3 font-bold text-white hover:bg-brand-secondary">Ke beranda</a>
            <a href="{{ route('public.news.index') }}" class="rounded-xl px-6 py-3 font-bold text-brand-primary ring-1 ring-brand-primary-200 hover:bg-brand-primary-50">Lihat berita</a>
        </div>
    </section>
</x-layouts.public>
