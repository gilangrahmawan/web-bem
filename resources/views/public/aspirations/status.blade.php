@php
    use App\Enums\AspirationStatus;
    use App\Support\Format;
@endphp
<x-layouts.public title="Cek Status Aspirasi" description="Pantau perkembangan dan tanggapan aspirasi Anda dengan kode tracking." noindex>
    <x-public.page-header eyebrow="Layanan Mahasiswa" title="Cek Status Aspirasi"
        subtitle="Masukkan kode tracking yang Anda terima saat mengirim aspirasi." />

    <div class="mx-auto max-w-3xl space-y-8 px-4 py-12 sm:px-6">
        <form method="POST" action="{{ route('public.aspirations.check') }}" class="space-y-4 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            @csrf
            <div>
                <label for="tracking_code" class="block text-sm font-semibold text-slate-800">Kode tracking</label>
                <input id="tracking_code" name="tracking_code" type="text" required maxlength="20" value="{{ old('tracking_code') }}"
                    placeholder="ASP-XXXX-XXXX" autocomplete="off" autocapitalize="characters"
                    class="mt-1 w-full rounded-xl border-slate-300 px-4 py-2.5 font-mono tracking-widest uppercase ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-primary">
                @error('tracking_code') <p class="mt-1 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
            </div>

            @include('public.partials.turnstile')

            <button type="submit" @disabled(! $siteKey)
                class="rounded-xl bg-brand-primary px-6 py-3 font-bold text-white hover:bg-brand-secondary disabled:cursor-not-allowed disabled:opacity-50">
                Cek status
            </button>
        </form>

        @if ($aspiration)
            <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-mono text-lg font-extrabold tracking-widest text-brand-primary">{{ $aspiration->tracking_code }}</p>
                    <span @class([
                        'rounded-full px-3 py-1 text-sm font-bold',
                        'bg-emerald-100 text-emerald-800' => $aspiration->status === AspirationStatus::Resolved,
                        'bg-slate-200 text-slate-800' => $aspiration->status === AspirationStatus::Rejected,
                        'bg-amber-100 text-amber-900' => ! $aspiration->status->isClosed(),
                    ])>{{ $aspiration->status->publicLabel() }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">Kategori: {{ $aspiration->category->label() }} · Dikirim {{ Format::date($aspiration->created_at) }}</p>

                @if ($aspiration->status === AspirationStatus::Resolved && filled($aspiration->response))
                    <div class="mt-6 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
                        <h2 class="font-extrabold text-emerald-900">Tanggapan BEM</h2>
                        <p class="mt-2 whitespace-pre-line text-emerald-950">{{ $aspiration->response }}</p>
                    </div>
                @endif

                @if ($aspiration->status === AspirationStatus::Rejected && filled($aspiration->rejection_reason))
                    <div class="mt-6 rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-300">
                        <h2 class="font-extrabold text-slate-900">Alasan aspirasi tidak dapat ditindaklanjuti</h2>
                        <p class="mt-2 whitespace-pre-line text-slate-800">{{ $aspiration->rejection_reason }}</p>
                    </div>
                @endif

                <h2 class="mt-8 font-extrabold text-brand-primary">Riwayat</h2>
                <ol class="mt-3 space-y-3 border-l-2 border-brand-primary-200 pl-5">
                    {{-- Hanya status & tanggal. Catatan internal dan nama pengurus tidak ditampilkan. --}}
                    @foreach ($aspiration->logs as $log)
                        <li>
                            <span class="block font-semibold text-slate-900">{{ $log->to_status->publicLabel() }}</span>
                            <span class="block text-sm text-slate-500">{{ Format::date($log->created_at) }}, {{ $log->created_at->format('H.i') }} WIB</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-layouts.public>
