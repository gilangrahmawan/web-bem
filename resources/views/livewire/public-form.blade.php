<div class="mx-auto w-full max-w-3xl px-4 py-8 sm:py-12" x-data x-on:form-page-changed.window="window.scrollTo({ top: 0, behavior: 'smooth' })">
    @if ($preview)
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900" role="status">
            Mode pratinjau — jawaban tidak disimpan dan tidak dihitung sebagai respons.
        </div>
    @endif

    {{-- Kartu judul --}}
    <header class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="h-2.5 bg-brand-primary"></div>
        <div class="px-5 py-6 sm:px-8">
            <h1 class="text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $formTitle }}</h1>
            @if ($formDescription)
                <p class="mt-3 whitespace-pre-line text-base text-slate-700">{{ $formDescription }}</p>
            @endif
            @if ($stage === 'form' && collect($sections)->flatMap(fn ($s) => $s['questions'])->contains('required', true))
                <p class="mt-4 text-sm text-red-700">* Wajib diisi</p>
            @endif
        </div>
    </header>

    @if ($stage === 'closed')
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center shadow-sm sm:px-8">
            <p class="text-lg font-bold text-slate-900">{{ $isFuture ? 'Form ini belum dibuka.' : 'Form ini sudah tidak menerima jawaban.' }}</p>
            @if ($isFuture)
                <p class="mt-2 text-slate-700">Dibuka {{ $opensAt->translatedFormat('j F Y, H:i') }} WIB.</p>
            @endif
        </section>
    @elseif ($stage === 'login')
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center shadow-sm sm:px-8">
            <p class="text-lg font-bold text-slate-900">Form ini khusus pengurus BEM.</p>
            <p class="mt-2 text-slate-700">Masuk dengan akun pengurus aktif untuk mengisi form ini.</p>
            <a href="{{ $loginUrl }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-brand-primary px-6 font-semibold text-white hover:opacity-90 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-accent">Masuk</a>
        </section>
    @elseif ($stage === 'already')
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center shadow-sm sm:px-8">
            <p class="text-lg font-bold text-slate-900">Anda sudah mengisi form ini.</p>
            <p class="mt-2 text-slate-700">Form ini hanya bisa diisi satu kali. Jawaban yang sudah dikirim tidak bisa diubah.</p>
        </section>
    @elseif ($stage === 'done')
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-5 py-8 shadow-sm sm:px-8" role="status">
            <p class="text-lg font-bold text-slate-900">{{ $result['message'] ?? 'Terima kasih.' }}</p>
            @if (! empty($result['score']))
                <div class="mt-5 rounded-xl bg-slate-50 px-4 py-4">
                    <p class="text-sm font-semibold text-slate-600">Nilai Anda</p>
                    <p class="text-3xl font-extrabold text-brand-primary">{{ rtrim(rtrim(number_format($result['score']['value'], 2, ',', '.'), '0'), ',') }} <span class="text-lg font-semibold text-slate-600">/ {{ rtrim(rtrim(number_format($result['score']['max'], 2, ',', '.'), '0'), ',') }}</span></p>
                    @if ($result['score']['passed'] !== null)
                        <p class="mt-1 font-semibold {{ $result['score']['passed'] ? 'text-green-800' : 'text-red-700' }}">{{ $result['score']['passed'] ? 'Lulus' : 'Belum lulus' }}</p>
                    @endif
                </div>
            @elseif (! empty($result['pending_grading']))
                <p class="mt-3 text-slate-700">Nilai akan tersedia setelah semua jawaban selesai dinilai.</p>
            @endif
            @if (! empty($result['preview']))
                <p class="mt-3 text-sm text-amber-800">Pratinjau: semua jawaban valid. Tidak ada data yang disimpan.</p>
            @endif
        </section>
    @elseif ($stage === 'start')
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white px-5 py-8 shadow-sm sm:px-8">
            <ul class="space-y-2 text-slate-700">
                @if ($timeLimit)
                    <li>Waktu pengerjaan: <strong>{{ $timeLimit }} menit</strong>, dihitung sejak tombol "Mulai" ditekan. Saat waktu habis, jawaban yang sudah terisi otomatis dikirim.</li>
                @endif
                <li>Jawaban tersimpan otomatis selama pengerjaan.</li>
                <li>Setelah dikirim, jawaban tidak bisa diubah.</li>
            </ul>
            <button type="button" wire:click="begin" wire:loading.attr="disabled" class="mt-6 inline-flex min-h-11 items-center rounded-xl bg-brand-primary px-6 font-semibold text-white hover:opacity-90 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-accent">Mulai</button>
        </section>
    @else
        @php
            $total = count($sections);
            $current = $sections[$page] ?? null;
            $isLast = $page >= $total - 1;
        @endphp

        @if ($secondsLeft !== null)
            <div class="sticky top-2 z-20 mt-4 flex justify-end"
                x-data="{ left: {{ (int) $secondsLeft }}, timer: null, fmt(s) { return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0') } }"
                x-init="timer = setInterval(() => { if (left > 0) { left-- } else { clearInterval(timer); $wire.submit() } }, 1000)">
                <span class="rounded-full px-4 py-2 font-mono text-base font-bold shadow" :class="left <= 60 ? 'bg-red-700 text-white' : 'bg-brand-primary text-white'" role="timer" aria-live="off">
                    Sisa waktu <span x-text="fmt(left)"></span>
                </span>
            </div>
        @endif

        @if ($isQuiz && ! $preview)
            <div x-data x-init="document.addEventListener('visibilitychange', () => { if (document.hidden) $wire.tabSwitched() })"></div>
        @endif

        @if ($total > 1)
            <div class="mt-4" aria-label="Kemajuan">
                <div class="flex justify-between text-sm font-semibold text-slate-700">
                    <span>Bagian {{ $page + 1 }} dari {{ $total }}</span>
                    <span>{{ (int) round(($page + 1) / $total * 100) }}%</span>
                </div>
                <div class="mt-1 h-2 rounded-full bg-slate-200"><div class="h-2 rounded-full bg-brand-primary" style="width: {{ ($page + 1) / $total * 100 }}%"></div></div>
            </div>
        @endif

        @error('form')
            <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 font-semibold text-red-800" role="alert">{{ $message }}</div>
        @enderror

        <form wire:submit="submit" class="mt-4 space-y-4" novalidate>
            @if ($current && ($current['title'] || $current['description']) && $total > 1)
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="bg-brand-primary px-5 py-3 text-white sm:px-8"><h2 class="font-bold">{{ $current['title'] ?: 'Bagian '.($page + 1) }}</h2></div>
                    @if ($current['description'])
                        <p class="whitespace-pre-line px-5 py-4 text-slate-700 sm:px-8">{{ $current['description'] }}</p>
                    @endif
                </div>
            @endif

            @foreach ($current['questions'] ?? [] as $q)
                @php
                    $id = $q['id'];
                    $inputId = 'q'.$id;
                    $hasError = $errors->has('answers.'.$id) || $errors->has('uploads.'.$id);
                    $field = 'w-full min-h-11 rounded-xl border bg-white px-4 py-2.5 text-base text-slate-900 focus:outline-none focus:ring-4 focus:ring-brand-accent/60 '.($hasError ? 'border-red-600' : 'border-slate-400');
                @endphp
                <fieldset wire:key="q-{{ $id }}" class="rounded-2xl border bg-white px-5 py-5 shadow-sm sm:px-8 {{ $hasError ? 'border-red-600' : 'border-slate-200' }}" @if ($hasError) aria-invalid="true" @endif>
                    @if ($q['type'] === 'text_block')
                        <legend class="sr-only">{{ $q['label'] }}</legend>
                        <h3 class="text-lg font-bold text-slate-900">{{ $q['label'] }}</h3>
                        @if ($q['help'])<p class="mt-2 whitespace-pre-line text-slate-700">{{ $q['help'] }}</p>@endif
                    @else
                        <legend class="float-left w-full">
                            <span class="text-base font-semibold text-slate-900">{{ $q['label'] }}</span>
                            @if ($q['required'])<span class="text-red-700" aria-hidden="true"> *</span><span class="sr-only"> (wajib)</span>@endif
                            @if ($q['points'])<span class="ml-2 text-sm font-normal text-slate-600">{{ $q['points'] }} poin</span>@endif
                        </legend>
                        <div class="clear-both pt-1">
                            @if ($q['help'])<p class="mb-3 whitespace-pre-line text-sm text-slate-600" id="{{ $inputId }}-help">{{ $q['help'] }}</p>@endif

                            @switch($q['type'])
                                @case('short_text')
                                    <input type="text" id="{{ $inputId }}" wire:model.blur="answers.{{ $id }}" maxlength="{{ $q['max_length'] }}" class="{{ $field }}" aria-label="{{ $q['label'] }}" @if ($q['required']) required @endif>
                                    @break
                                @case('paragraph')
                                    <textarea id="{{ $inputId }}" wire:model.blur="answers.{{ $id }}" rows="4" maxlength="{{ $q['max_length'] }}" class="{{ $field }}" aria-label="{{ $q['label'] }}"></textarea>
                                    @break
                                @case('number')
                                    <input type="number" step="any" inputmode="decimal" id="{{ $inputId }}" wire:model.blur="answers.{{ $id }}" @if ($q['min'] !== null) min="{{ $q['min'] }}" @endif @if ($q['max'] !== null) max="{{ $q['max'] }}" @endif class="{{ $field }} sm:max-w-xs" aria-label="{{ $q['label'] }}">
                                    @break
                                @case('email')
                                    <input type="email" autocomplete="email" id="{{ $inputId }}" wire:model.blur="answers.{{ $id }}" class="{{ $field }}" aria-label="{{ $q['label'] }}">
                                    @break
                                @case('phone')
                                    <input type="tel" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" id="{{ $inputId }}" wire:model.blur="answers.{{ $id }}" class="{{ $field }} sm:max-w-xs" aria-label="{{ $q['label'] }}">
                                    @break
                                @case('date')
                                    <input type="date" id="{{ $inputId }}" wire:model.live="answers.{{ $id }}" class="{{ $field }} sm:max-w-xs" aria-label="{{ $q['label'] }}">
                                    @break
                                @case('time')
                                    <input type="time" id="{{ $inputId }}" wire:model.live="answers.{{ $id }}" class="{{ $field }} sm:max-w-xs" aria-label="{{ $q['label'] }}">
                                    @break
                                @case('multiple_choice')
                                    <div class="space-y-1" role="radiogroup">
                                        @foreach ($q['choices'] as $i => $choice)
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-slate-50">
                                                <input type="radio" name="{{ $inputId }}" value="{{ $choice }}" wire:model.live="answers.{{ $id }}.selected" class="size-5 accent-brand-primary">
                                                <span class="text-slate-900">{{ $choice }}</span>
                                            </label>
                                        @endforeach
                                        @if ($q['allow_other'])
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-slate-50">
                                                <input type="radio" name="{{ $inputId }}" value="__other__" wire:model.live="answers.{{ $id }}.selected" class="size-5 accent-brand-primary">
                                                <span class="text-slate-900">Lainnya:</span>
                                                <input type="text" wire:model.blur="answers.{{ $id }}.other" maxlength="500" class="min-h-11 flex-1 border-0 border-b border-slate-400 px-1 focus:border-brand-primary focus:outline-none" aria-label="Jawaban lainnya">
                                            </label>
                                        @endif
                                    </div>
                                    @break
                                @case('checkboxes')
                                    <div class="space-y-1">
                                        @foreach ($q['choices'] as $i => $choice)
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-slate-50">
                                                <input type="checkbox" value="{{ $choice }}" wire:model.live="answers.{{ $id }}.selected" class="size-5 rounded accent-brand-primary">
                                                <span class="text-slate-900">{{ $choice }}</span>
                                            </label>
                                        @endforeach
                                        @if ($q['allow_other'])
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 hover:bg-slate-50">
                                                <input type="checkbox" value="__other__" wire:model.live="answers.{{ $id }}.selected" class="size-5 rounded accent-brand-primary">
                                                <span class="text-slate-900">Lainnya:</span>
                                                <input type="text" wire:model.blur="answers.{{ $id }}.other" maxlength="500" class="min-h-11 flex-1 border-0 border-b border-slate-400 px-1 focus:border-brand-primary focus:outline-none" aria-label="Jawaban lainnya">
                                            </label>
                                        @endif
                                    </div>
                                    @break
                                @case('dropdown')
                                    <select id="{{ $inputId }}" wire:model.live="answers.{{ $id }}.selected" class="{{ $field }} sm:max-w-md" aria-label="{{ $q['label'] }}">
                                        <option value="">Pilih</option>
                                        @foreach ($q['choices'] as $choice)
                                            <option value="{{ $choice }}">{{ $choice }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('linear_scale')
                                    <div class="flex flex-wrap items-end gap-2" role="radiogroup" aria-label="{{ $q['label'] }}">
                                        @if ($q['min_label'])<span class="w-full text-sm text-slate-600 sm:w-auto sm:self-center">{{ $q['min_label'] }}</span>@endif
                                        @for ($n = $q['min']; $n <= $q['max']; $n++)
                                            <label class="flex min-h-11 min-w-11 cursor-pointer flex-col items-center justify-center rounded-lg px-1 hover:bg-slate-50">
                                                <span class="text-sm text-slate-700">{{ $n }}</span>
                                                <input type="radio" name="{{ $inputId }}" value="{{ $n }}" wire:model.live="answers.{{ $id }}" class="size-5 accent-brand-primary">
                                            </label>
                                        @endfor
                                        @if ($q['max_label'])<span class="w-full text-sm text-slate-600 sm:w-auto sm:self-center">{{ $q['max_label'] }}</span>@endif
                                    </div>
                                    @break
                                @case('file_upload')
                                    @php $list = $files[$id] ?? []; @endphp
                                    <div x-data="{
                                            over: false, uploading: false, progress: 0,
                                            async shrink(file) {
                                                if (! /^image\/(jpeg|png|webp)$/.test(file.type) || ! ('createImageBitmap' in window)) return file
                                                try {
                                                    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
                                                    const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height))
                                                    if (scale === 1 && file.size <= 1.5 * 1024 * 1024) { bitmap.close?.(); return file }
                                                    const canvas = document.createElement('canvas')
                                                    canvas.width = Math.round(bitmap.width * scale); canvas.height = Math.round(bitmap.height * scale)
                                                    const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height)
                                                    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height); bitmap.close?.()
                                                    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.88))
                                                    return blob ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file
                                                } catch (e) { return file }
                                            },
                                            async send(list) {
                                                const picked = [...list]
                                                if (! picked.length) return
                                                const files = await Promise.all(picked.map(f => this.shrink(f)))
                                                this.uploading = true
                                                $wire.uploadMultiple('uploads.{{ $id }}', files, () => { this.uploading = false }, () => { this.uploading = false }, (e) => { this.progress = e.detail.progress })
                                            },
                                        }">
                                        @if (count($list) < $q['max_files'])
                                            <label class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-5 text-center"
                                                :class="over ? 'border-brand-primary bg-slate-50' : 'border-slate-400'"
                                                x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false"
                                                x-on:drop.prevent="over = false; send($event.dataTransfer.files)">
                                                <span class="font-semibold text-brand-primary">Pilih berkas atau seret ke sini</span>
                                                <span class="text-sm text-slate-600">{{ $q['kinds_label'] }} · gambar maks. 2 MB, dokumen maks. 10 MB · maks. {{ $q['max_files'] }} berkas</span>
                                                <input type="file" class="sr-only" accept="{{ $q['accept'] }}" @if ($q['max_files'] > 1) multiple @endif x-on:change="send($event.target.files); $event.target.value = ''">
                                            </label>
                                        @endif
                                        <p class="mt-2 text-sm text-slate-600" x-show="uploading" x-cloak>Mengunggah… <span x-text="progress + '%'"></span></p>
                                        @if ($list)
                                            <ul class="mt-3 space-y-2">
                                                @foreach ($list as $index => $file)
                                                    <li class="flex min-h-11 items-center justify-between gap-3 rounded-lg bg-slate-50 px-3" wire:key="f-{{ $id }}-{{ $index }}">
                                                        <span class="truncate text-slate-900">{{ $file['name'] }}</span>
                                                        <button type="button" wire:click="removeFile({{ $id }}, {{ $index }})" class="min-h-11 shrink-0 px-2 font-semibold text-red-700 hover:underline">Hapus</button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    @break
                            @endswitch

                            @error('answers.'.$id)<p class="mt-2 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>@enderror
                            @error('uploads.'.$id)<p class="mt-2 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </fieldset>
            @endforeach

            @if ($isLast && $needsTurnstile)
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-8">
                    @if ($siteKey)
                        <div wire:ignore x-data="{ widget: null }"
                            x-init="
                                const draw = () => { widget = window.turnstile.render($el, { sitekey: @js($siteKey), language: 'id', theme: 'light', callback: (t) => $wire.set('turnstileToken', t, false), 'expired-callback': () => $wire.set('turnstileToken', '', false) }) }
                                if (window.turnstile) { draw() } else {
                                    (window.__bemTurnstileQueue ||= []).push(draw)
                                    if (! document.getElementById('cf-turnstile-script')) {
                                        window.__bemTurnstileReady = () => window.__bemTurnstileQueue.forEach(f => f())
                                        const s = document.createElement('script'); s.id = 'cf-turnstile-script'; s.async = true
                                        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=__bemTurnstileReady'
                                        document.head.appendChild(s)
                                    }
                                }"
                            x-on:form-turnstile-reset.window="if (window.turnstile && widget !== null) window.turnstile.reset(widget)"></div>
                    @else
                        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">Verifikasi keamanan belum dikonfigurasi, sehingga form ini belum bisa dikirim. Hubungi BEM melalui halaman kontak.</p>
                    @endif
                    @error('turnstile')<p class="mt-2 text-sm font-semibold text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <div class="flex gap-3">
                    @if ($page > 0)
                        <button type="button" wire:click="back" class="inline-flex min-h-11 items-center rounded-xl border border-slate-400 bg-white px-5 font-semibold text-slate-900 hover:bg-slate-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-accent">Kembali</button>
                    @endif
                    @if (! $isLast)
                        <button type="button" wire:click="next" wire:loading.attr="disabled" class="inline-flex min-h-11 items-center rounded-xl bg-brand-primary px-6 font-semibold text-white hover:opacity-90 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-accent">Berikutnya</button>
                    @else
                        <button type="submit" wire:loading.attr="disabled" wire:target="submit,uploads" class="inline-flex min-h-11 items-center rounded-xl bg-brand-primary px-6 font-semibold text-white hover:opacity-90 disabled:opacity-60 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-accent">Kirim</button>
                    @endif
                </div>
                <p class="text-sm text-slate-600" aria-live="polite">
                    <span wire:loading.delay wire:target="answers">Menyimpan…</span>
                    <span wire:offline class="font-semibold text-red-700">Koneksi terputus — jawaban tetap tersimpan di halaman ini dan dikirim saat online kembali.</span>
                </p>
            </div>
        </form>
    @endif
</div>
