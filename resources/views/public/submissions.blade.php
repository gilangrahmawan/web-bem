<x-layouts.public title="Pengajuan Kegiatan" description="Alur pengajuan proposal & LPJ kegiatan ke BEM UBHINUS, template dokumen, dan cara mendapatkan akun pengaju.">
    <x-public.page-header eyebrow="Layanan" title="Pengajuan Kegiatan"
        subtitle="Proposal dan LPJ kegiatan UKM, HMJ, dan panitia diajukan secara online setelah login dengan akun pengaju." />

    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_20rem] lg:px-8">
        <section class="space-y-8">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <h2 class="text-xl font-extrabold text-brand-primary">Alur pengajuan</h2>
                <ol class="mt-5 space-y-4">
                    @foreach ([
                        ['Login dengan akun pengaju', 'Akun dibuat oleh Sekretaris BEM, satu akun per orang dan terhubung ke organisasi Anda.'],
                        ['Buat kegiatan & ajukan proposal', 'Isi data kegiatan, unggah proposal (PDF, maks. 10 MB). Sistem memberi peringatan bila tanggal bentrok dengan agenda lain.'],
                        ['Review proposal', 'Sekretaris memeriksa kelengkapan, Bendahara meninjau anggaran, lalu Ketua/Wakil memutuskan. Bila perlu revisi, Anda mendapat catatan dan bisa mengunggah ulang.'],
                        ['Kegiatan berlangsung', 'Proposal yang disetujui otomatis tampil di Kalender Kegiatan.'],
                        ['Unggah LPJ', 'Paling lambat '.\App\Models\StudentActivity::lpjDeadlineDays().' hari setelah kegiatan selesai, beserta realisasi anggaran. LPJ ditinjau dengan alur yang sama.'],
                        ['Selesai & arsip', 'Riwayat kegiatan tersimpan atas nama organisasi.'],
                    ] as $i => [$title, $text])
                        <li class="flex gap-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-primary font-bold text-white">{{ $i + 1 }}</span>
                            <div>
                                <p class="font-bold text-slate-900">{{ $title }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <h2 class="text-xl font-extrabold text-brand-primary">Template dokumen</h2>
                @if ($templates->isNotEmpty())
                    <ul class="mt-4 divide-y divide-slate-100">
                        @foreach ($templates as $template)
                            <li class="flex items-center justify-between gap-4 py-3">
                                <span>
                                    <span class="block font-semibold text-slate-900">{{ $template->title }}</span>
                                    <span class="text-xs text-slate-500">{{ $template->category->label() }}</span>
                                </span>
                                <a href="{{ route('public.downloads.file', $template) }}" class="shrink-0 rounded-xl px-4 py-2 text-sm font-bold text-brand-primary ring-1 ring-brand-primary-200 hover:bg-brand-primary-50">Unduh</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-slate-600">Template proposal dan LPJ tersedia di <a class="font-semibold text-brand-secondary underline" href="{{ route('public.downloads.index') }}">Pusat Unduhan</a>.</p>
                @endif
            </div>
        </section>

        <aside class="space-y-4">
            <div class="rounded-3xl bg-brand-primary p-6 text-white">
                <h2 class="text-lg font-extrabold">Belum punya akun?</h2>
                <p class="mt-2 text-sm text-white/85">Hubungi Sekretaris BEM dengan menyebutkan nama, organisasi, dan email Anda.</p>
                <ul class="mt-4 space-y-2 text-sm">
                    @if (filled($site['contact_email'] ?? null))
                        <li>Email: <a class="font-semibold text-brand-accent underline" href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a></li>
                    @endif
                    @if (filled($site['contact_phone'] ?? null))
                        <li>WA/Telepon: <span class="font-semibold">{{ $site['contact_phone'] }}</span></li>
                    @endif
                    <li><a class="font-semibold text-brand-accent underline" href="{{ route('public.contact') }}">Halaman kontak</a></li>
                </ul>
            </div>
            <a href="{{ url('/panel-bem/login') }}" class="block rounded-3xl bg-white p-6 font-bold text-brand-primary shadow-sm ring-1 ring-slate-200 hover:bg-brand-primary-50">
                Sudah punya akun? Masuk →
            </a>
        </aside>
    </div>
</x-layouts.public>
