@php
    use App\Enums\ApplicantStatus;
    use App\Support\Format;
    $stage = $applicant?->publicStage();
    $input = 'mt-1 w-full rounded-xl border-slate-300 px-4 py-2.5 ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary';
@endphp
<x-layouts.public title="Pengumuman Oprec" description="Cek status pendaftaran, jadwal interview, dan hasil open recruitment BEM UBHINUS." noindex>
    <x-public.page-header eyebrow="Layanan Mahasiswa" title="Cek Status & Hasil Oprec"
        subtitle="Masukkan kode pendaftaran dan NIM yang Anda gunakan saat mendaftar." />

    <div class="mx-auto max-w-3xl space-y-8 px-4 py-12 sm:px-6">
        <form method="POST" action="{{ route('public.recruitment.check') }}" class="space-y-4 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="registration_code" class="block text-sm font-semibold text-slate-800">Kode pendaftaran</label>
                    <input id="registration_code" name="registration_code" type="text" required maxlength="20" value="{{ old('registration_code') }}"
                        placeholder="REG-XXXX-XXXX" autocomplete="off" autocapitalize="characters" class="{{ $input }} font-mono tracking-widest uppercase">
                </div>
                <div>
                    <label for="nim" class="block text-sm font-semibold text-slate-800">NIM</label>
                    <input id="nim" name="nim" type="text" required maxlength="20" value="{{ old('nim') }}" autocomplete="off" class="{{ $input }} uppercase">
                </div>
            </div>
            @error('registration_code') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
            @error('nim') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror

            @include('public.partials.turnstile')

            <button type="submit" @disabled(! $siteKey)
                class="rounded-xl bg-brand-primary px-6 py-3 font-bold text-white hover:bg-brand-secondary disabled:cursor-not-allowed disabled:opacity-50">
                Cek status
            </button>
        </form>

        @if ($applicant)
            {{-- Hanya status publik & jadwal. Nilai, catatan interview, dan nama pengurus TIDAK ditampilkan. --}}
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-mono text-lg font-extrabold tracking-widest text-brand-primary">{{ $applicant->registration_code }}</p>
                    <span @class([
                        'rounded-full px-3 py-1 text-sm font-bold',
                        'bg-emerald-100 text-emerald-800' => $stage === ApplicantStatus::Accepted,
                        'bg-slate-200 text-slate-800' => $stage === ApplicantStatus::Rejected,
                        'bg-amber-100 text-amber-900' => ! $stage->isFinal(),
                    ])>{{ match ($stage) {
                        ApplicantStatus::Registered => 'Berkas sedang diperiksa',
                        ApplicantStatus::DocumentPassed => 'Lolos seleksi berkas',
                        ApplicantStatus::Interview => 'Tahap interview',
                        ApplicantStatus::Accepted => 'Diterima',
                        ApplicantStatus::Rejected => 'Belum lolos',
                    } }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">
                    {{ $applicant->name }} · {{ $applicant->recruitment->name }} · Pilihan: {{ $applicant->firstChoice?->name }}@if ($applicant->secondChoice), {{ $applicant->secondChoice->name }}@endif
                </p>

                @if ($stage === ApplicantStatus::Interview && $applicant->interview_at)
                    <div class="mt-6 rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
                        <h2 class="font-extrabold text-amber-900">Jadwal interview</h2>
                        <p class="mt-2 text-amber-950">{{ Format::date($applicant->interview_at) }}, pukul {{ $applicant->interview_at->format('H.i') }} WIB</p>
                        <p class="mt-1 break-words text-amber-950">Tempat/link: {{ $applicant->interview_location }}</p>
                    </div>
                @endif

                @if ($stage === ApplicantStatus::Accepted)
                    <div class="mt-6 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
                        <h2 class="font-extrabold text-emerald-900">Selamat!</h2>
                        <p class="mt-2 text-emerald-950">Anda diterima sebagai pengurus BEM UBHINUS di divisi <strong>{{ $applicant->acceptedDivision?->name }}</strong>. Informasi selanjutnya akan disampaikan melalui WhatsApp atau email.</p>
                    </div>
                @elseif ($stage === ApplicantStatus::Rejected)
                    <div class="mt-6 rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-300">
                        <p class="text-slate-800">Terima kasih atas minat dan waktu Anda. Anda belum lolos pada gelombang ini — semoga kita bisa berkolaborasi di kesempatan lain.</p>
                    </div>
                @elseif (! $applicant->recruitment->resultsPublished())
                    <p class="mt-6 text-sm text-slate-600">Hasil akhir seleksi akan tampil di halaman ini setelah diumumkan.</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.public>
