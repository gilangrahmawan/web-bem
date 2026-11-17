<x-filament-panels::page>
    @php
        $statusColor = ['draft' => 'gray', 'published' => 'success', 'closed' => 'danger'][$form->status->value] ?? 'gray';
        $choiceTypes = ['multiple_choice', 'checkboxes', 'dropdown'];
        $gradable = ['multiple_choice', 'checkboxes', 'dropdown', 'short_text', 'paragraph', 'file_upload'];
    @endphp

    <div class="bem-fb">
        <x-filament::tabs class="bem-fb__tabs">
            <x-filament::tabs.item :active="$tab === 'pertanyaan'" wire:click="$set('tab', 'pertanyaan')" icon="heroicon-o-queue-list">Pertanyaan</x-filament::tabs.item>
            <x-filament::tabs.item :active="$tab === 'jawaban'" wire:click="$set('tab', 'jawaban')" icon="heroicon-o-chart-bar">
                Jawaban <x-slot name="badge">{{ $form->responses()->whereNotNull('submitted_at')->count() }}</x-slot>
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$tab === 'pengaturan'" wire:click="$set('tab', 'pengaturan')" icon="heroicon-o-cog-6-tooth">Pengaturan</x-filament::tabs.item>
        </x-filament::tabs>

        <div class="bem-fb__status">
            <x-filament::badge :color="$statusColor">{{ $form->status->label() }}</x-filament::badge>
            @if ($form->is_quiz)<x-filament::badge color="info">Kuis</x-filament::badge>@endif
            <label class="bem-fb__link">
                <span class="bem-fb__sr">Link form</span>
                <input type="text" readonly value="{{ $publicUrl }}" x-on:focus="$el.select()" aria-label="Link form">
            </label>
            @if ($form->status->value === 'draft')
                <span class="bem-fb__muted">Link aktif setelah form diterbitkan.</span>
            @endif
        </div>

        @if ($tab === 'pertanyaan')
            @if ($hasResponses && $canEdit)
                <div class="bem-fb__warn" role="status">Form ini sudah punya jawaban. Mengganti jenis pertanyaan, menghapus pertanyaan, atau menghapus/mengubah opsi akan meminta konfirmasi.</div>
            @endif

            <div class="bem-fb__layout" x-data="{ active: null }"
                x-on:form-question-added.window="active = $event.detail.id; $nextTick(() => document.getElementById('fq-' + $event.detail.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' }))">
                <div class="bem-fb__column">
                    {{-- Kartu judul --}}
                    <div class="bem-fb__card bem-fb__card--head">
                        <input type="text" class="bem-fb__title" wire:model.blur="formData.title" maxlength="200" placeholder="Judul form" aria-label="Judul form" @disabled(! $canEdit)>
                        <textarea class="bem-fb__desc" wire:model.blur="formData.description" rows="2" maxlength="3000" placeholder="Deskripsi form" aria-label="Deskripsi form" @disabled(! $canEdit)></textarea>
                    </div>

                    <div class="bem-fb__list"
                        @if ($canEdit) x-sortable x-on:end.stop="$wire.reorder($event.target.sortable.toArray())" @endif>
                        @foreach ($sections as $sIndex => $section)
                            @php $sid = $section['id']; @endphp
                            @if ($sIndex > 0 || count($sections) > 1)
                                <div class="bem-fb__section" wire:key="s-{{ $sid }}" @if ($sIndex > 0) x-sortable-item="s:{{ $sid }}" @endif>
                                    <div class="bem-fb__section-bar">
                                        @if ($sIndex > 0 && $canEdit)<span class="bem-fb__handle" x-sortable-handle title="Seret untuk memindahkan">⠿</span>@endif
                                        <span>Bagian {{ $sIndex + 1 }} dari {{ count($sections) }}</span>
                                    </div>
                                    <div class="bem-fb__card bem-fb__card--section">
                                        <input type="text" class="bem-fb__label" wire:model.blur="sectionData.{{ $sid }}.title" maxlength="200" placeholder="Judul bagian" aria-label="Judul bagian" @disabled(! $canEdit)>
                                        <textarea class="bem-fb__help" wire:model.blur="sectionData.{{ $sid }}.description" rows="1" maxlength="2000" placeholder="Deskripsi bagian (opsional)" aria-label="Deskripsi bagian" @disabled(! $canEdit)></textarea>
                                        @if ($sIndex > 0 && $canEdit)
                                            <div class="bem-fb__footer">
                                                <button type="button" class="bem-fb__icon-btn" wire:click="deleteSection({{ $sid }})" wire:confirm="Hapus bagian ini? Pertanyaannya pindah ke bagian sebelumnya.">
                                                    <x-filament::icon icon="heroicon-o-trash" class="bem-fb__icon" /> Hapus bagian
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @foreach ($section['questions'] as $question)
                                @php
                                    $qid = $question->getKey();
                                    $d = $questionData[$qid] ?? [];
                                    $type = $d['type'] ?? $question->type->value;
                                @endphp
                                <div id="fq-{{ $qid }}" class="bem-fb__card bem-fb__card--q" wire:key="q-{{ $qid }}" x-sortable-item="q:{{ $qid }}"
                                    x-on:click="active = {{ $qid }}" x-bind:class="active === {{ $qid }} && 'is-active'">
                                    @if ($canEdit)<div class="bem-fb__drag" x-sortable-handle title="Seret untuk memindahkan">⠿</div>@endif

                                    <div class="bem-fb__row">
                                        <input type="text" class="bem-fb__label" wire:model.blur="questionData.{{ $qid }}.label" maxlength="500"
                                            placeholder="{{ $type === 'text_block' ? 'Judul' : 'Pertanyaan' }}" aria-label="Teks pertanyaan" @disabled(! $canEdit)>
                                        <select class="bem-fb__select" wire:model.live="questionData.{{ $qid }}.type" aria-label="Jenis pertanyaan" @disabled(! $canEdit)>
                                            @foreach ($types as $t)
                                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <textarea class="bem-fb__help" wire:model.blur="questionData.{{ $qid }}.help_text" rows="1" maxlength="2000"
                                        placeholder="{{ $type === 'text_block' ? 'Teks / keterangan' : 'Deskripsi (opsional)' }}" aria-label="Deskripsi pertanyaan" @disabled(! $canEdit)></textarea>

                                    {{-- Editor per jenis --}}
                                    @if (in_array($type, $choiceTypes, true))
                                        <ul class="bem-fb__choices">
                                            @foreach ($d['options']['choices'] ?? [] as $i => $choice)
                                                <li wire:key="c-{{ $qid }}-{{ $i }}-{{ md5($choice) }}">
                                                    <span class="bem-fb__bullet">{{ $type === 'multiple_choice' ? '○' : ($type === 'checkboxes' ? '☐' : ($i + 1).'.') }}</span>
                                                    <input type="text" wire:model.blur="questionData.{{ $qid }}.options.choices.{{ $i }}" maxlength="200" aria-label="Opsi {{ $i + 1 }}" @disabled(! $canEdit)>
                                                    @if ($form->is_quiz)
                                                        <label class="bem-fb__key" title="Tandai sebagai jawaban benar">
                                                            <input type="{{ $type === 'checkboxes' ? 'checkbox' : 'radio' }}" name="key-{{ $qid }}"
                                                                @if ($type === 'checkboxes') wire:model.live="questionData.{{ $qid }}.correct_answer.choices" value="{{ $choice }}"
                                                                @else x-on:click="$wire.set('questionData.{{ $qid }}.correct_answer.choices', [{{ \Illuminate\Support\Js::from($choice) }}])" @checked(in_array($choice, $d['correct_answer']['choices'] ?? [], true)) @endif
                                                                @disabled(! $canEdit)>
                                                            Benar
                                                        </label>
                                                    @endif
                                                    @if ($canEdit && count($d['options']['choices'] ?? []) > 1)
                                                        <button type="button" class="bem-fb__x" wire:click="removeChoice({{ $qid }}, {{ $i }})" aria-label="Hapus opsi {{ $i + 1 }}">×</button>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if ($canEdit)
                                            <div class="bem-fb__inline">
                                                <button type="button" class="bem-fb__link-btn" wire:click="addChoice({{ $qid }})">+ Tambah opsi</button>
                                                @if ($type !== 'dropdown')
                                                    <label class="bem-fb__check"><input type="checkbox" wire:model.live="questionData.{{ $qid }}.options.allow_other"> Opsi "Lainnya"</label>
                                                @endif
                                                @if ($type === 'checkboxes')
                                                    <label class="bem-fb__num">Min. dipilih <input type="number" min="0" wire:model.blur="questionData.{{ $qid }}.validation.min_selected"></label>
                                                    <label class="bem-fb__num">Maks. dipilih <input type="number" min="0" wire:model.blur="questionData.{{ $qid }}.validation.max_selected"></label>
                                                    @if ($form->is_quiz)
                                                        <label class="bem-fb__check"><input type="checkbox" wire:model.live="questionData.{{ $qid }}.options.partial_credit"> Nilai parsial</label>
                                                    @endif
                                                @endif
                                            </div>
                                        @endif
                                    @elseif (in_array($type, ['short_text', 'paragraph'], true))
                                        <div class="bem-fb__preview">{{ $type === 'short_text' ? 'Jawaban singkat' : 'Jawaban panjang' }}</div>
                                        @if ($canEdit)
                                            <div class="bem-fb__inline">
                                                <label class="bem-fb__num">Min. karakter <input type="number" min="0" wire:model.blur="questionData.{{ $qid }}.validation.min_length"></label>
                                                <label class="bem-fb__num">Maks. karakter <input type="number" min="0" max="10000" wire:model.blur="questionData.{{ $qid }}.validation.max_length"></label>
                                                @if ($type === 'short_text')
                                                    <label class="bem-fb__num">Format
                                                        <select wire:model.live="questionData.{{ $qid }}.validation.pattern">
                                                            <option value="">Bebas</option>
                                                            @foreach ($patterns as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                                        </select>
                                                    </label>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($form->is_quiz && $type === 'short_text')
                                            <label class="bem-fb__block">Jawaban benar (satu per baris; huruf besar/kecil & spasi diabaikan)
                                                <textarea rows="2" wire:model.blur="keyTexts.{{ $qid }}" @disabled(! $canEdit)></textarea>
                                            </label>
                                        @endif
                                    @elseif ($type === 'number')
                                        <div class="bem-fb__inline">
                                            <label class="bem-fb__num">Min. <input type="number" step="any" wire:model.blur="questionData.{{ $qid }}.validation.min" @disabled(! $canEdit)></label>
                                            <label class="bem-fb__num">Maks. <input type="number" step="any" wire:model.blur="questionData.{{ $qid }}.validation.max" @disabled(! $canEdit)></label>
                                        </div>
                                    @elseif ($type === 'linear_scale')
                                        <div class="bem-fb__inline">
                                            <label class="bem-fb__num">Dari <select wire:model.live="questionData.{{ $qid }}.options.scale_min" @disabled(! $canEdit)><option value="0">0</option><option value="1">1</option></select></label>
                                            <label class="bem-fb__num">sampai <select wire:model.live="questionData.{{ $qid }}.options.scale_max" @disabled(! $canEdit)><option value="5">5</option><option value="10">10</option></select></label>
                                            <label class="bem-fb__num">Label awal <input type="text" maxlength="50" wire:model.blur="questionData.{{ $qid }}.options.min_label" @disabled(! $canEdit)></label>
                                            <label class="bem-fb__num">Label akhir <input type="text" maxlength="50" wire:model.blur="questionData.{{ $qid }}.options.max_label" @disabled(! $canEdit)></label>
                                        </div>
                                    @elseif ($type === 'file_upload')
                                        <div class="bem-fb__inline">
                                            <label class="bem-fb__num">Maks. berkas
                                                <select wire:model.live="questionData.{{ $qid }}.options.max_files" @disabled(! $canEdit)><option value="1">1</option><option value="2">2</option><option value="3">3</option></select>
                                            </label>
                                            @foreach ($fileKinds as $kind)
                                                <label class="bem-fb__check"><input type="checkbox" value="{{ $kind }}" wire:model.live="questionData.{{ $qid }}.options.file_kinds" @disabled(! $canEdit)> {{ \App\Enums\DriveFileKind::from($kind)->label() }}</label>
                                            @endforeach
                                        </div>
                                        <p class="bem-fb__muted">Gambar maks. 2 MB (disimpan WebP), dokumen maks. 10 MB. ZIP tidak diterima. Berkas disimpan privat.</p>
                                    @elseif (in_array($type, ['email', 'phone', 'date', 'time'], true))
                                        <div class="bem-fb__preview">{{ ['email' => 'nama@contoh.com', 'phone' => '08xxxxxxxxxx', 'date' => 'Tanggal', 'time' => 'Waktu'][$type] }}</div>
                                    @endif

                                    <div class="bem-fb__footer">
                                        @if ($form->is_quiz && in_array($type, $gradable, true))
                                            <label class="bem-fb__num">Poin <input type="number" min="0" max="1000" wire:model.blur="questionData.{{ $qid }}.points" @disabled(! $canEdit)></label>
                                        @endif
                                        @if ($canEdit)
                                            <button type="button" class="bem-fb__icon-btn" wire:click="duplicateQuestion({{ $qid }})" title="Gandakan" aria-label="Gandakan pertanyaan">
                                                <x-filament::icon icon="heroicon-o-document-duplicate" class="bem-fb__icon" />
                                            </button>
                                            <button type="button" class="bem-fb__icon-btn" wire:click="deleteQuestion({{ $qid }})" title="Hapus" aria-label="Hapus pertanyaan">
                                                <x-filament::icon icon="heroicon-o-trash" class="bem-fb__icon" />
                                            </button>
                                            @if ($type !== 'text_block')
                                                <label class="bem-fb__check bem-fb__required"><input type="checkbox" wire:model.live="questionData.{{ $qid }}.is_required"> Wajib</label>
                                            @endif
                                        @elseif ($d['is_required'] ?? false)
                                            <span class="bem-fb__muted">Wajib</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                @if ($canEdit)
                    <div class="bem-fb__toolbar" role="toolbar" aria-label="Tambah item">
                        <button type="button" x-on:click="$wire.addQuestion(active)" title="Tambah pertanyaan" aria-label="Tambah pertanyaan">
                            <x-filament::icon icon="heroicon-o-plus-circle" class="bem-fb__icon" /><span>Pertanyaan</span>
                        </button>
                        <button type="button" x-on:click="$wire.addQuestion(active, 'text_block')" title="Tambah judul & teks" aria-label="Tambah judul dan teks">
                            <x-filament::icon icon="heroicon-o-document-text" class="bem-fb__icon" /><span>Judul</span>
                        </button>
                        <button type="button" wire:click="addSection" title="Tambah bagian" aria-label="Tambah bagian">
                            <x-filament::icon icon="heroicon-o-bars-2" class="bem-fb__icon" /><span>Bagian</span>
                        </button>
                    </div>
                @endif
            </div>
            <p class="bem-fb__saved" aria-live="polite">
                <span wire:loading.delay wire:target="questionData,formData,sectionData,keyTexts,reorder">Menyimpan…</span>
                <span wire:loading.remove wire:target="questionData,formData,sectionData,keyTexts,reorder">Semua perubahan tersimpan otomatis.</span>
            </p>
        @elseif ($tab === 'jawaban')
            @include('filament.resources.forms.responses')
        @else
            <form wire:submit="saveSettings" class="bem-fb__settings">
                {{ $this->settingsForm }}
                @if ($canEdit)
                    <div><x-filament::button type="submit">Simpan pengaturan</x-filament::button></div>
                @endif
            </form>
        @endif
    </div>

    <style>
        .bem-fb { --brand: #232361; --accent: #FFDB1A; --line: rgb(148 163 184 / .4); --soft: rgb(248 250 252); --card: #fff; --text: rgb(15 23 42); --muted: rgb(71 85 105);
            display: grid; gap: 1rem; font-size: .9rem; color: var(--text); }
        .dark .bem-fb { --soft: rgb(255 255 255 / .04); --card: rgb(24 24 27); --text: rgb(241 245 249); --muted: rgb(161 161 170); }
        .bem-fb__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        .bem-fb__status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .bem-fb__link input { min-width: 18rem; max-width: 100%; padding: .4rem .7rem; border: 1px solid var(--line); border-radius: .6rem; background: var(--soft); color: var(--text); font-size: .8rem; }
        .bem-fb__muted { color: var(--muted); font-size: .8rem; }
        .bem-fb__warn { padding: .7rem 1rem; border-radius: .75rem; background: rgb(254 243 199); color: rgb(120 53 15); font-weight: 600; }
        .bem-fb__layout { display: grid; grid-template-columns: minmax(0, 48rem) auto; gap: 1rem; justify-content: center; align-items: start; }
        .bem-fb__column, .bem-fb__list { display: grid; gap: .9rem; }
        .bem-fb__card { position: relative; background: var(--card); border: 1px solid var(--line); border-radius: .9rem; padding: 1.1rem 1.4rem; display: grid; gap: .6rem; }
        .bem-fb__card--head { border-top: 10px solid var(--brand); }
        .bem-fb__card--section { border-top-left-radius: 0; }
        .bem-fb__card--q { border-left: 6px solid transparent; transition: border-color .15s, box-shadow .15s; }
        .bem-fb__card--q.is-active, .bem-fb__card--q:focus-within { border-left-color: var(--accent); box-shadow: 0 2px 10px rgb(0 0 0 / .08); }
        .bem-fb__section-bar { display: inline-flex; gap: .5rem; align-items: center; background: var(--brand); color: #fff; font-weight: 700; padding: .35rem .9rem; border-radius: .6rem .6rem 0 0; font-size: .8rem; }
        .bem-fb__drag { position: absolute; top: .15rem; left: 50%; transform: translateX(-50%); cursor: grab; color: var(--muted); letter-spacing: .2rem; user-select: none; }
        .bem-fb__handle { cursor: grab; }
        .bem-fb__row { display: flex; gap: .75rem; flex-wrap: wrap; }
        .bem-fb__title, .bem-fb__label, .bem-fb__desc, .bem-fb__help { width: 100%; background: transparent; border: 0; border-bottom: 1px solid var(--line); color: var(--text); padding: .45rem .1rem; resize: vertical; }
        .bem-fb__title { font-size: 1.6rem; font-weight: 800; }
        .bem-fb__label { flex: 1 1 16rem; width: auto; font-size: 1rem; font-weight: 600; background: var(--soft); padding: .65rem .75rem; border-radius: .4rem .4rem 0 0; }
        .bem-fb__help { font-size: .85rem; color: var(--muted); }
        .bem-fb__title:focus, .bem-fb__label:focus, .bem-fb__desc:focus, .bem-fb__help:focus { outline: none; border-bottom: 2px solid var(--brand); }
        .dark .bem-fb__title:focus, .dark .bem-fb__label:focus, .dark .bem-fb__desc:focus, .dark .bem-fb__help:focus { border-bottom-color: var(--accent); }
        .bem-fb__select, .bem-fb select, .bem-fb input[type=number], .bem-fb__num input[type=text], .bem-fb__block textarea { border: 1px solid var(--line); border-radius: .5rem; padding: .45rem .6rem; background: var(--card); color: var(--text); min-height: 2.5rem; }
        .bem-fb__select { flex: 0 0 13rem; }
        .bem-fb__choices { display: grid; gap: .35rem; }
        .bem-fb__choices li { display: flex; align-items: center; gap: .6rem; }
        .bem-fb__choices li > input[type=text] { flex: 1; border: 0; border-bottom: 1px solid transparent; background: transparent; color: var(--text); padding: .45rem .2rem; min-height: 2.5rem; }
        .bem-fb__choices li > input[type=text]:hover { border-bottom-color: var(--line); }
        .bem-fb__choices li > input[type=text]:focus { outline: none; border-bottom: 2px solid var(--brand); }
        .bem-fb__bullet { color: var(--muted); width: 1.4rem; text-align: center; }
        .bem-fb__key { display: inline-flex; gap: .3rem; align-items: center; font-size: .78rem; color: rgb(21 128 61); font-weight: 600; }
        .bem-fb__x { width: 2.5rem; height: 2.5rem; border-radius: 999px; font-size: 1.3rem; color: var(--muted); }
        .bem-fb__x:hover, .bem-fb__icon-btn:hover { background: var(--soft); }
        .bem-fb__inline { display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: center; }
        .bem-fb__link-btn { color: var(--brand); font-weight: 600; min-height: 2.5rem; }
        .dark .bem-fb__link-btn { color: var(--accent); }
        .bem-fb__check, .bem-fb__num { display: inline-flex; align-items: center; gap: .4rem; min-height: 2.5rem; }
        .bem-fb__num input[type=number] { width: 6rem; }
        .bem-fb__block { display: grid; gap: .3rem; font-size: .8rem; color: var(--muted); }
        .bem-fb__preview { color: var(--muted); border-bottom: 1px dotted var(--line); padding: .5rem .2rem; width: min(100%, 22rem); }
        .bem-fb__footer { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .4rem; border-top: 1px solid var(--line); padding-top: .6rem; margin-top: .2rem; }
        .bem-fb__icon-btn { display: inline-flex; align-items: center; gap: .3rem; min-width: 2.75rem; min-height: 2.75rem; justify-content: center; border-radius: 999px; color: var(--muted); padding: 0 .5rem; }
        .bem-fb__icon { width: 1.25rem; height: 1.25rem; }
        .bem-fb__required { padding-left: .6rem; border-left: 1px solid var(--line); }
        .bem-fb__toolbar { position: sticky; top: 5rem; display: grid; gap: .25rem; background: var(--card); border: 1px solid var(--line); border-radius: .9rem; padding: .35rem; box-shadow: 0 2px 8px rgb(0 0 0 / .08); }
        .bem-fb__toolbar button { display: flex; flex-direction: column; align-items: center; gap: .1rem; min-width: 3.5rem; min-height: 3rem; padding: .35rem; border-radius: .6rem; color: var(--brand); font-size: .7rem; font-weight: 600; }
        .dark .bem-fb__toolbar button { color: var(--accent); }
        .bem-fb__toolbar button:hover { background: var(--soft); }
        .bem-fb__saved { text-align: center; color: var(--muted); font-size: .8rem; }
        .bem-fb__settings { display: grid; gap: 1rem; max-width: 48rem; }
        .bem-fb__bars { display: grid; gap: .45rem; }
        .bem-fb__bar { display: grid; grid-template-columns: minmax(6rem, 14rem) 1fr 4.5rem; gap: .6rem; align-items: center; }
        .bem-fb__bar-track { height: 1.1rem; background: var(--soft); border-radius: .3rem; overflow: hidden; border: 1px solid var(--line); }
        .bem-fb__bar-fill { height: 100%; background: var(--brand); }
        .dark .bem-fb__bar-fill { background: var(--accent); }
        .bem-fb__bar-fill.is-correct { background: rgb(21 128 61); }
        .bem-fb__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .75rem; }
        .bem-fb__stat { background: var(--card); border: 1px solid var(--line); border-radius: .75rem; padding: .8rem 1rem; }
        .bem-fb__stat strong { display: block; font-size: 1.4rem; color: var(--brand); }
        .dark .bem-fb__stat strong { color: var(--accent); }
        .bem-fb__table { width: 100%; border-collapse: collapse; }
        .bem-fb__table th, .bem-fb__table td { text-align: left; padding: .55rem .6rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        .bem-fb__table th { font-size: .75rem; text-transform: uppercase; color: var(--muted); }
        .bem-fb__samples { display: grid; gap: .3rem; }
        .bem-fb__samples li { background: var(--soft); border-radius: .4rem; padding: .4rem .6rem; }
        @media (max-width: 1024px) {
            .bem-fb__layout { grid-template-columns: minmax(0, 1fr); }
            .bem-fb__toolbar { position: sticky; bottom: .75rem; top: auto; grid-auto-flow: column; justify-content: center; z-index: 20; }
            .bem-fb__select { flex-basis: 100%; }
            .bem-fb__bar { grid-template-columns: 1fr 3.5rem; }
            .bem-fb__bar > span:first-child { grid-column: 1 / -1; }
        }
    </style>
</x-filament-panels::page>
