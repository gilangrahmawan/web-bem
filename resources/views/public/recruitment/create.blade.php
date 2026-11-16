@php
    use App\Support\Format;
    $input = 'mt-1 w-full rounded-xl border-slate-300 px-4 py-2.5 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary';
@endphp
<x-layouts.public title="Open Recruitment" description="Pendaftaran pengurus BEM UBHINUS: divisi yang dibuka, syarat berkas, dan formulir pendaftaran online.">
    <x-slot:head>
        @vite('resources/js/public-image-resize.js')
    </x-slot:head>
    <x-public.page-header eyebrow="Layanan Mahasiswa" title="Open Recruitment"
        subtitle="Bergabung menjadi pengurus BEM Universitas Bhinneka Nusantara." />

    <div class="mx-auto grid max-w-5xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_18rem] lg:px-8">
        <section>
            @if ($submittedCode)
                <div class="rounded-3xl bg-emerald-50 p-6 ring-1 ring-emerald-200 sm:p-8" role="status">
                    <p class="text-sm font-bold tracking-widest text-emerald-800 uppercase">Pendaftaran terkirim</p>
                    <h2 class="mt-2 text-2xl font-extrabold text-emerald-900">Terima kasih telah mendaftar!</h2>
                    <p class="mt-3 text-emerald-900">Simpan kode pendaftaran berikut. Gunakan kode ini bersama NIM Anda untuk melihat jadwal interview dan hasil seleksi:</p>
                    <p class="mt-4 inline-block rounded-2xl bg-white px-5 py-3 font-mono text-2xl font-extrabold tracking-widest text-brand-primary ring-1 ring-emerald-200 select-all">{{ $submittedCode }}</p>
                    <p class="mt-4 text-sm text-emerald-900">Kode hanya ditampilkan sekali. Catat atau tangkap layar sekarang.</p>
                    <a href="{{ route('public.recruitment.results') }}" class="mt-6 inline-block rounded-xl bg-brand-primary px-5 py-3 font-bold text-white hover:bg-brand-secondary">Cek status pendaftaran</a>
                </div>
            @elseif (! $recruitment)
                <x-public.empty-state title="Pendaftaran sedang ditutup"
                    :message="$upcoming
                        ? 'Gelombang berikutnya, '.$upcoming->name.', dibuka '.Format::date($upcoming->opens_at).'.'
                        : 'Belum ada jadwal open recruitment. Pantau media sosial BEM UBHINUS untuk informasi terbaru.'" />
                @error('recruitment') <p class="mt-4 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
            @else
                <div class="mb-6 rounded-3xl bg-brand-primary-50 p-6 ring-1 ring-brand-primary-200">
                    <h2 class="text-xl font-extrabold text-brand-primary">{{ $recruitment->name }}</h2>
                    <p class="mt-1 text-sm text-slate-700">Dibuka sampai {{ Format::date($recruitment->closes_at) }}, {{ $recruitment->closes_at->format('H.i') }} WIB</p>
                    @if (filled($recruitment->description))
                        <p class="mt-3 whitespace-pre-line text-slate-700">{{ $recruitment->description }}</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('public.recruitment.store') }}" enctype="multipart/form-data" data-processing-form
                    class="space-y-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                    @csrf

                    <fieldset class="grid gap-4 sm:grid-cols-2">
                        <legend class="mb-2 text-lg font-extrabold text-brand-primary">Data diri</legend>
                        <div class="sm:col-span-2">
                            <label for="name" class="block text-sm font-semibold text-slate-800">Nama lengkap</label>
                            <input id="name" name="name" type="text" required maxlength="100" value="{{ old('name') }}" autocomplete="name" class="{{ $input }}">
                            @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="nim" class="block text-sm font-semibold text-slate-800">NIM</label>
                            <input id="nim" name="nim" type="text" required maxlength="20" value="{{ old('nim') }}" autocomplete="off" class="{{ $input }} uppercase">
                            @error('nim') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="batch_year" class="block text-sm font-semibold text-slate-800">Angkatan</label>
                            <input id="batch_year" name="batch_year" type="number" required min="2000" max="{{ now()->year + 1 }}" value="{{ old('batch_year', now()->year) }}" class="{{ $input }}">
                            @error('batch_year') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="prodi" class="block text-sm font-semibold text-slate-800">Program studi</label>
                            <input id="prodi" name="prodi" type="text" required maxlength="100" value="{{ old('prodi') }}" class="{{ $input }}">
                            @error('prodi') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-semibold text-slate-800">Nomor WhatsApp</label>
                            <input id="phone" name="phone" type="tel" required maxlength="20" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" autocomplete="tel" class="{{ $input }}">
                            @error('phone') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-800">Email</label>
                            <input id="email" name="email" type="email" required maxlength="150" value="{{ old('email') }}" autocomplete="email" class="{{ $input }}">
                            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-4 sm:grid-cols-2">
                        <legend class="mb-2 text-lg font-extrabold text-brand-primary">Pilihan divisi</legend>
                        <div>
                            <label for="first_choice_division_id" class="block text-sm font-semibold text-slate-800">Pilihan 1</label>
                            <select id="first_choice_division_id" name="first_choice_division_id" required class="{{ $input }}">
                                <option value="">Pilih divisi…</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((string) old('first_choice_division_id') === (string) $division->id)>{{ $division->name }}</option>
                                @endforeach
                            </select>
                            @error('first_choice_division_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="second_choice_division_id" class="block text-sm font-semibold text-slate-800">Pilihan 2 <span class="font-normal text-slate-500">(opsional)</span></label>
                            <select id="second_choice_division_id" name="second_choice_division_id" class="{{ $input }}">
                                <option value="">Tidak ada</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected((string) old('second_choice_division_id') === (string) $division->id)>{{ $division->name }}</option>
                                @endforeach
                            </select>
                            @error('second_choice_division_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    <div>
                        <label for="motivation" class="block text-sm font-semibold text-slate-800">Motivasi bergabung</label>
                        <textarea id="motivation" name="motivation" rows="5" required minlength="30" maxlength="3000" class="{{ $input }}">{{ old('motivation') }}</textarea>
                        @error('motivation') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="experience" class="block text-sm font-semibold text-slate-800">Pengalaman organisasi <span class="font-normal text-slate-500">(opsional)</span></label>
                        <textarea id="experience" name="experience" rows="4" maxlength="3000" class="{{ $input }}">{{ old('experience') }}</textarea>
                        @error('experience') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <fieldset class="grid gap-4 sm:grid-cols-2">
                        <legend class="mb-2 text-lg font-extrabold text-brand-primary">Berkas</legend>
                        <div>
                            <label for="cv" class="block text-sm font-semibold text-slate-800">CV <span class="font-normal text-slate-500">(PDF, maks. 2 MB)</span></label>
                            <input id="cv" name="cv" type="file" required accept="application/pdf,.pdf" class="mt-1 block w-full text-sm"
                                data-max-kb="2048" data-status="#cv-status">
                            <p id="cv-status" class="mt-1 hidden text-sm text-slate-600" aria-live="polite"></p>
                            @error('cv') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="photo" class="block text-sm font-semibold text-slate-800">Foto diri <span class="font-normal text-slate-500">(JPG/PNG/WebP — foto besar dari HP otomatis diperkecil)</span></label>
                            <input id="photo" name="photo" type="file" required accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm"
                                data-resize-image="1600" data-status="#photo-status">
                            <p id="photo-status" class="mt-1 hidden text-sm text-slate-600" aria-live="polite"></p>
                            @error('photo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 size-5 accent-brand-primary" @checked(old('consent'))>
                        <span class="text-sm text-slate-700">
                            Saya menyatakan data yang saya isi benar dan menyetujui data ini digunakan BEM UBHINUS hanya untuk proses seleksi.
                            Bila saya tidak diterima, CV, foto, nomor WhatsApp, dan email saya akan dihapus otomatis
                            {{ \App\Services\RecruitmentService::retentionMonths() }} bulan setelah pendaftaran ditutup;
                            nama, NIM, prodi, angkatan, dan pilihan divisi tetap disimpan untuk rekap.
                        </span>
                    </label>
                    @error('consent') <p class="-mt-4 text-sm text-red-700">{{ $message }}</p> @enderror

                    @include('public.partials.turnstile')

                    <button type="submit" @disabled(! $siteKey)
                        class="w-full rounded-xl bg-brand-primary px-6 py-3 font-bold text-white hover:bg-brand-secondary disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                        Kirim pendaftaran
                    </button>
                    <p data-processing-message role="status" class="hidden text-sm font-semibold text-slate-600">
                        Sedang mengunggah dan memproses berkas… mohon tunggu, jangan tutup halaman ini.
                    </p>
                </form>
            @endif
        </section>

        <aside class="space-y-4 text-sm text-slate-600">
            <div class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-200">
                <h2 class="font-extrabold text-brand-primary">Tahapan seleksi</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5">
                    <li>Pendaftaran & seleksi berkas.</li>
                    <li>Interview — jadwal dapat dilihat di halaman cek status.</li>
                    <li>Pengumuman hasil.</li>
                </ol>
            </div>
            <a href="{{ route('public.recruitment.results') }}" class="block rounded-3xl bg-brand-primary p-6 font-bold text-white hover:bg-brand-secondary">
                Sudah mendaftar? Cek status & hasil →
            </a>
        </aside>
    </div>
</x-layouts.public>
