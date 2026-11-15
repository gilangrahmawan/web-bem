<x-layouts.public title="Kirim Aspirasi" description="Sampaikan aspirasi, kritik, atau masukan untuk kampus dan BEM UBHINUS. Boleh anonim.">
    <x-public.page-header eyebrow="Layanan Mahasiswa" title="Kirim Aspirasi"
        subtitle="Sampaikan aspirasi, kritik, atau masukan Anda. Boleh tanpa nama — identitas dan alamat IP pengirim anonim tidak disimpan." />

    <div class="mx-auto grid max-w-5xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_18rem] lg:px-8">
        <section>
            @if ($submittedCode)
                <div class="rounded-3xl bg-emerald-50 p-6 ring-1 ring-emerald-200 sm:p-8" role="status">
                    <p class="text-sm font-bold tracking-widest text-emerald-800 uppercase">Aspirasi terkirim</p>
                    <h2 class="mt-2 text-2xl font-extrabold text-emerald-900">Terima kasih atas aspirasi Anda!</h2>
                    <p class="mt-3 text-emerald-900">Simpan kode tracking berikut untuk memantau perkembangan dan tanggapan:</p>
                    <p class="mt-4 inline-block rounded-2xl bg-white px-5 py-3 font-mono text-2xl font-extrabold tracking-widest text-brand-primary ring-1 ring-emerald-200 select-all">{{ $submittedCode }}</p>
                    <p class="mt-4 text-sm text-emerald-900">Kode hanya ditampilkan sekali. Catat atau tangkap layar sekarang.</p>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('public.aspirations.status') }}" class="rounded-xl bg-brand-primary px-5 py-3 text-center font-bold text-white hover:bg-brand-secondary">Cek status aspirasi</a>
                        <a href="{{ route('public.aspirations.create') }}" class="rounded-xl px-5 py-3 text-center font-bold text-brand-primary ring-1 ring-brand-primary-200 hover:bg-brand-primary-50">Kirim aspirasi lain</a>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('public.aspirations.store') }}" class="space-y-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                    @csrf

                    <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <input type="checkbox" name="is_anonymous" value="1" class="mt-1 size-5 accent-brand-primary" @checked(old('is_anonymous'))
                            onchange="document.querySelectorAll('[data-identity]').forEach(el => el.classList.toggle('hidden', this.checked))">
                        <span>
                            <span class="block font-bold text-slate-900">Kirim sebagai anonim</span>
                            <span class="block text-sm text-slate-600">Nama, kontak, dan alamat IP Anda tidak akan disimpan.</span>
                        </span>
                    </label>

                    <div data-identity class="grid gap-4 sm:grid-cols-2 {{ old('is_anonymous') ? 'hidden' : '' }}">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-slate-800">Nama <span class="font-normal text-slate-500">(opsional)</span></label>
                            <input id="name" name="name" type="text" maxlength="100" value="{{ old('name') }}" autocomplete="name"
                                class="mt-1 w-full rounded-xl border-slate-300 px-4 py-2.5 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary">
                            @error('name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="contact" class="block text-sm font-semibold text-slate-800">Kontak <span class="font-normal text-slate-500">(email/WA, opsional)</span></label>
                            <input id="contact" name="contact" type="text" maxlength="100" value="{{ old('contact') }}"
                                class="mt-1 w-full rounded-xl border-slate-300 px-4 py-2.5 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary">
                            @error('contact') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-semibold text-slate-800">Kategori</label>
                        <select id="category" name="category" required
                            class="mt-1 w-full rounded-xl border-slate-300 px-4 py-2.5 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary">
                            <option value="">Pilih kategori…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                        @error('category') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="body" class="block text-sm font-semibold text-slate-800">Isi aspirasi</label>
                        <textarea id="body" name="body" rows="7" required minlength="20" maxlength="5000"
                            placeholder="Ceritakan aspirasi Anda dengan jelas: apa masalahnya, di mana, sejak kapan, dan harapan Anda."
                            class="mt-1 w-full rounded-xl border-slate-300 px-4 py-3 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary">{{ old('body') }}</textarea>
                        @error('body') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>

                    @include('public.partials.turnstile')

                    <button type="submit" @disabled(! $siteKey)
                        class="w-full rounded-xl bg-brand-primary px-6 py-3 font-bold text-white hover:bg-brand-secondary disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                        Kirim aspirasi
                    </button>
                </form>
            @endif
        </section>

        <aside class="space-y-4 text-sm text-slate-600">
            <div class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-200">
                <h2 class="font-extrabold text-brand-primary">Alur aspirasi</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5">
                    <li>Aspirasi diterima dan ditinjau Divisi Humas.</li>
                    <li>Bila perlu, dinaikkan ke Ketua/Wakil dan dikaji Divisi Kajian.</li>
                    <li>Tanggapan dapat dilihat lewat kode tracking.</li>
                </ol>
            </div>
            <a href="{{ route('public.aspirations.status') }}" class="block rounded-3xl bg-brand-primary p-6 font-bold text-white hover:bg-brand-secondary">
                Sudah punya kode tracking? Cek status →
            </a>
        </aside>
    </div>
</x-layouts.public>
