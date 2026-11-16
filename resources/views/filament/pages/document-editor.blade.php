<x-filament-panels::page>
    <div class="bem-doc"
        @if ($editing) wire:poll.10s="autosave" @elseif ($canEditAtAll) wire:poll.15s="checkLock" @endif>
        <div class="bem-doc__status" role="status" aria-live="polite">
            @if ($editing)
                <span class="bem-doc__dot is-on"></span>
                <span>
                    Mode edit · tersimpan otomatis tiap ±10 detik
                    @if ($savedAt) · terakhir {{ $savedAt }} @endif
                    <span wire:loading wire:target="autosave,save">· menyimpan…</span>
                </span>
            @elseif ($lockedBy)
                <span class="bem-doc__dot is-locked"></span>
                <span>Sedang diedit oleh <strong>{{ $lockedBy }}</strong>. Anda melihat versi tersimpan; editor terbuka otomatis bila kunci dilepas (maks. {{ $lockMinutes }} menit tanpa aktivitas).</span>
            @else
                <span class="bem-doc__dot"></span>
                <span>Mode baca — Anda tidak punya akses edit di folder ini.</span>
            @endif
        </div>

        @if ($editing)
            {{ $this->form }}
        @else
            <article class="bem-doc__read">
                <h1>{{ $record->title }}</h1>
                {{-- Isi sudah disanitasi (profil purifier 'document'). --}}
                {!! $safeContent !!}
            </article>
        @endif
    </div>

    <style>
        .bem-doc { display: grid; gap: 1rem; max-width: 56rem; }
        .bem-doc__status { display: flex; gap: .6rem; align-items: center; font-size: .85rem; color: rgb(100 116 139); }
        .bem-doc__dot { width: .6rem; height: .6rem; border-radius: 999px; background: rgb(148 163 184); flex-shrink: 0; }
        .bem-doc__dot.is-on { background: #16A34A; }
        .bem-doc__dot.is-locked { background: #D97706; }
        .bem-doc__read { padding: 2rem; border-radius: .9rem; border: 1px solid rgb(148 163 184 / .35); background: #fff; color: #111; line-height: 1.6; }
        .dark .bem-doc__read { background: rgb(255 255 255 / .04); color: inherit; }
        .bem-doc__read h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 1rem; }
        .bem-doc__read h2 { font-size: 1.25rem; font-weight: 700; margin: 1rem 0 .5rem; }
        .bem-doc__read h3 { font-size: 1.1rem; font-weight: 700; margin: .8rem 0 .4rem; }
        .bem-doc__read p { margin: .4rem 0; }
        .bem-doc__read ul { list-style: disc; padding-left: 1.5rem; }
        .bem-doc__read ol { list-style: decimal; padding-left: 1.5rem; }
        .bem-doc__read table { border-collapse: collapse; }
        .bem-doc__read th, .bem-doc__read td { border: 1px solid rgb(148 163 184); padding: .3rem .5rem; }
    </style>
</x-filament-panels::page>
