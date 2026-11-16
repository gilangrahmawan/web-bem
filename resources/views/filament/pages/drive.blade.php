<x-filament-panels::page>
    <div class="bem-drive"
        x-data="{
            view: (() => { try { return localStorage.getItem('bem-drive-view') || 'grid' } catch (e) { return 'grid' } })(),
            dragging: false,
            queue: [],
            uploading: false,
            preview: null,
            canCreate: @js($canCreate),
            maxBytes: @js($maxUploadBytes),
            setView(v) { this.view = v; try { localStorage.setItem('bem-drive-view', v) } catch (e) {} },
            pick() { this.$refs.picker.click() },
            async enqueue(fileList) {
                if (! this.canCreate) { return }
                const files = Array.from(fileList || [])
                if (! files.length) { return }
                files.forEach(f => this.queue.push({ name: f.name, file: f, progress: 0, status: 'menunggu', message: '' }))
                if (! this.uploading) { await this.process() }
            },
            async process() {
                this.uploading = true
                let ok = 0, failed = 0
                for (const item of this.queue) {
                    if (item.status !== 'menunggu') { continue }
                    if (item.file.size > this.maxBytes) {
                        item.status = 'gagal'; item.message = 'Melebihi 10 MB'; failed++; continue
                    }
                    item.status = 'mengunggah'
                    // Satu berkas per request: aman untuk upload_max_filesize 12M & post_max_size 25M.
                    const result = await new Promise((resolve) => {
                        $wire.upload('upload', item.file,
                            async () => resolve(await $wire.storeUpload()),
                            () => resolve({ ok: false, message: 'Unggahan terputus atau ditolak server' }),
                            (e) => { item.progress = e.detail.progress },
                        )
                    })
                    if (result && result.ok) { item.status = 'selesai'; item.progress = 100; ok++ }
                    else { item.status = 'gagal'; item.message = (result && result.message) || 'Gagal'; failed++ }
                }
                this.uploading = false
                await $wire.uploadsFinished(ok, failed)
            },
            clearQueue() { this.queue = this.queue.filter(i => i.status === 'mengunggah' || i.status === 'menunggu') },
        }"
        x-on:dragover.prevent="if (canCreate) dragging = true"
        x-on:dragleave.prevent="if ($event.target === $el || ! $el.contains($event.relatedTarget)) dragging = false"
        x-on:drop.prevent="dragging = false; enqueue($event.dataTransfer.files)"
        x-on:keydown.escape.window="preview = null"
    >
        <input type="file" multiple class="bem-drive__hidden" x-ref="picker" accept="{{ $uploadAccept }}"
            x-on:change="enqueue($event.target.files); $event.target.value = ''">

        {{-- ============ Sidebar ============ --}}
        <aside class="bem-drive__sidebar">
            <x-filament::dropdown placement="bottom-start">
                <x-slot name="trigger">
                    <button type="button" class="bem-drive__new" @disabled(! $canCreate)
                        title="{{ $canCreate ? 'Buat atau unggah' : 'Buka folder yang bisa Anda isi untuk menambah item' }}">
                        <x-filament::icon icon="heroicon-o-plus" class="bem-drive__new-icon" />
                        <span>Baru</span>
                    </button>
                </x-slot>
                <x-filament::dropdown.list>
                    <x-filament::dropdown.list.item icon="heroicon-o-folder-plus" wire:click="mountAction('createFolder')">Folder baru</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="heroicon-o-arrow-up-tray" x-on:click="pick()">Upload berkas</x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item icon="heroicon-o-document-plus" wire:click="mountAction('createDocument')">Dokumen baru</x-filament::dropdown.list.item>
                </x-filament::dropdown.list>
            </x-filament::dropdown>

            <nav class="bem-drive__nav" aria-label="Navigasi Drive">
                @foreach ([
                    'home' => ['Beranda', 'heroicon-o-home'],
                    'my' => ['Drive Saya', 'heroicon-o-folder'],
                    'shared' => ['Dibagikan kepada saya', 'heroicon-o-users'],
                    'recent' => ['Terbaru', 'heroicon-o-clock'],
                    'starred' => ['Berbintang', 'heroicon-o-star'],
                    'trash' => ['Sampah', 'heroicon-o-trash'],
                ] as $key => [$label, $icon])
                    <button type="button" wire:click="go('{{ $key }}')" @class(['is-active' => $section === $key && trim($search) === ''])>
                        <x-filament::icon :icon="$icon" class="bem-drive__nav-icon" />
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="bem-drive__storage">
                <div class="bem-drive__storage-bar" role="progressbar" aria-valuenow="{{ $storage['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $storage['percent'] }}%" @class(['is-full' => $storage['percent'] >= 90])></span>
                </div>
                <p>{{ $storage['used'] }} dari {{ $storage['quota'] }} terpakai</p>
            </div>
        </aside>

        {{-- ============ Konten ============ --}}
        <section class="bem-drive__main">
            <div class="bem-drive__toolbar">
                <label class="bem-drive__search">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="bem-drive__nav-icon" />
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Cari di Drive" maxlength="100" aria-label="Cari di Drive">
                </label>
                <div class="bem-drive__toggle" role="group" aria-label="Tampilan">
                    <button type="button" x-on:click="setView('grid')" :class="{ 'is-active': view === 'grid' }" title="Tampilan kartu">
                        <x-filament::icon icon="heroicon-o-squares-2x2" class="bem-drive__nav-icon" />
                    </button>
                    <button type="button" x-on:click="setView('list')" :class="{ 'is-active': view === 'list' }" title="Tampilan daftar">
                        <x-filament::icon icon="heroicon-o-bars-3" class="bem-drive__nav-icon" />
                    </button>
                </div>
            </div>

            @if ($breadcrumbs->isNotEmpty())
                <nav class="bem-drive__crumbs" aria-label="Lokasi folder">
                    <button type="button" wire:click="go('{{ $seesAll ? 'my' : 'shared' }}')">{{ $seesAll ? 'Drive Saya' : 'Dibagikan' }}</button>
                    @foreach ($breadcrumbs as $crumb)
                        <span aria-hidden="true">›</span>
                        <button type="button" wire:click="go('folder', {{ $crumb->id }})" @class(['is-current' => $loop->last])>{{ $crumb->name }}</button>
                    @endforeach
                </nav>
            @endif

            <div class="bem-drive__head">
                <h2>{{ $title }}</h2>
                <div class="bem-drive__head-actions">
                    @if ($folderAccessLabel)
                        <span class="bem-drive__badge">Akses Anda: {{ $folderAccessLabel }}</span>
                    @endif
                    @if ($folder && Gate::allows('share', $folder))
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-user-plus"
                            wire:click="mountAction('share', {{ Js::from(['type' => 'folder', 'id' => $folder->id]) }})">Bagikan</x-filament::button>
                    @endif
                    @if ($zipUrl)
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" tag="a" :href="$zipUrl">Unduh ZIP</x-filament::button>
                    @endif
                    @if ($canCreate)
                        <x-filament::button size="sm" icon="heroicon-o-arrow-up-tray" x-on:click="pick()">Upload</x-filament::button>
                    @endif
                </div>
            </div>

            @if ($isTrash)
                <p class="bem-drive__hint">Item di Sampah dihapus permanen otomatis setelah {{ $trashDays }} hari.</p>
            @endif

            @php $isEmpty = collect($groups)->every(fn ($items) => $items->isEmpty()); @endphp

            @if ($isEmpty)
                <div class="bem-drive__empty">
                    <x-filament::icon :icon="$isTrash ? 'heroicon-o-trash' : 'heroicon-o-folder-open'" class="bem-drive__empty-icon" />
                    <p class="bem-drive__empty-title">
                        {{ match (true) {
                            trim($search) !== '' => 'Tidak ada yang cocok dengan pencarian.',
                            $isTrash => 'Sampah kosong.',
                            $section === 'shared' => 'Belum ada folder yang dibagikan kepada Anda.',
                            $section === 'starred' => 'Belum ada item berbintang.',
                            default => 'Belum ada isi di sini.',
                        } }}
                    </p>
                    @if ($canCreate)
                        <p>Seret berkas ke sini atau gunakan tombol <strong>Baru</strong>.</p>
                    @endif
                </div>
            @endif

            @foreach ($groups as $groupLabel => $items)
                @continue($items->isEmpty())
                <h3 class="bem-drive__group">{{ $groupLabel }}</h3>

                {{-- Kartu --}}
                <div class="bem-drive__grid" x-show="view === 'grid'">
                    @foreach ($items as $item)
                        <div class="bem-drive__card" wire:key="g-{{ $item['key'] }}">
                            @include('filament.pages.partials.drive-open', ['item' => $item, 'class' => 'bem-drive__card-body'])
                                <div class="bem-drive__thumb" @if (! empty($item['color'])) style="--kind: {{ $item['color'] }}" @endif>
                                    @if (! empty($item['thumb']))
                                        <img src="{{ $item['thumb'] }}" alt="" loading="lazy">
                                    @else
                                        <x-filament::icon :icon="$item['icon']" class="bem-drive__thumb-icon" />
                                    @endif
                                </div>
                                <div class="bem-drive__card-text">
                                    <span class="bem-drive__name" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                                    <span class="bem-drive__meta">{{ $item['trashed'] ? 'Dibuang '.$item['deleted'] : ($item['meta'] ?? '') }}</span>
                                </div>
                            </{{ $item['tag'] ?? 'button' }}>
                            @include('filament.pages.partials.drive-menu', ['item' => $item])
                        </div>
                    @endforeach
                </div>

                {{-- Daftar --}}
                <div class="bem-drive__list" x-show="view === 'list'" x-cloak>
                    @foreach ($items as $item)
                        <div class="bem-drive__row" wire:key="l-{{ $item['key'] }}">
                            @include('filament.pages.partials.drive-open', ['item' => $item, 'class' => 'bem-drive__row-body'])
                                <x-filament::icon :icon="$item['icon']" class="bem-drive__row-icon" :style="! empty($item['color']) ? 'color: '.$item['color'] : null" />
                                <span class="bem-drive__name" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                                <span class="bem-drive__meta">{{ $item['owner'] ?? '' }}</span>
                                <span class="bem-drive__meta">{{ $item['trashed'] ? 'Dibuang '.$item['deleted'] : $item['updated'] }}</span>
                                <span class="bem-drive__meta">{{ $item['meta'] ?? '' }}</span>
                            </{{ $item['tag'] ?? 'button' }}>
                            @include('filament.pages.partials.drive-menu', ['item' => $item])
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>

        {{-- ============ Drop zone ============ --}}
        <div class="bem-drive__drop" x-show="dragging" x-cloak x-transition.opacity>
            <div>
                <x-filament::icon icon="heroicon-o-arrow-up-tray" class="bem-drive__empty-icon" />
                <p>Lepaskan untuk mengunggah ke <strong>{{ $folder?->name ?? 'Drive' }}</strong></p>
                <p class="bem-drive__hint">Maks. 10 MB per berkas · PDF, Word, Excel, PowerPoint, gambar, ZIP, TXT/CSV</p>
            </div>
        </div>

        {{-- ============ Antrian upload ============ --}}
        <div class="bem-drive__queue" x-show="queue.length" x-cloak role="status" aria-live="polite">
            <div class="bem-drive__queue-head">
                <strong x-text="uploading ? 'Mengunggah…' : 'Unggahan selesai'"></strong>
                <button type="button" x-show="! uploading" x-on:click="clearQueue()" title="Tutup">✕</button>
            </div>
            <template x-for="(item, i) in queue" :key="i">
                <div class="bem-drive__queue-item">
                    <span class="bem-drive__name" x-text="item.name"></span>
                    <span class="bem-drive__meta" :class="{ 'is-error': item.status === 'gagal' }"
                        x-text="item.status === 'mengunggah' ? item.progress + '%' : (item.status === 'gagal' ? item.message : item.status)"></span>
                    <div class="bem-drive__queue-bar"><span :style="'width:' + item.progress + '%'"></span></div>
                </div>
            </template>
        </div>

        {{-- ============ Pratinjau ============ --}}
        <div class="bem-drive__preview" x-show="preview" x-cloak x-on:click.self="preview = null" role="dialog" aria-modal="true">
            <div class="bem-drive__preview-box">
                <div class="bem-drive__preview-head">
                    <strong x-text="preview?.name"></strong>
                    <div>
                        <a :href="preview?.download" class="bem-drive__preview-btn">Unduh</a>
                        <button type="button" class="bem-drive__preview-btn" x-on:click="preview = null">Tutup</button>
                    </div>
                </div>
                <template x-if="preview && preview.kind === 'image'">
                    <img :src="preview.url" :alt="preview.name" class="bem-drive__preview-img">
                </template>
                <template x-if="preview && preview.kind === 'pdf'">
                    <iframe :src="preview.url" class="bem-drive__preview-frame" title="Pratinjau PDF"></iframe>
                </template>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .bem-drive { --line: rgb(148 163 184 / .35); --soft: rgb(241 245 249); --brand: #232361; --accent: #FFDB1A;
            display: grid; grid-template-columns: 15rem 1fr; gap: 1.25rem; position: relative; min-height: 60vh; font-size: .875rem; }
        .dark .bem-drive { --soft: rgb(255 255 255 / .05); }
        .bem-drive__hidden { display: none; }
        .bem-drive__sidebar { display: flex; flex-direction: column; gap: 1rem; }
        .bem-drive__new { display: inline-flex; align-items: center; gap: .6rem; padding: .85rem 1.4rem; border-radius: 1rem;
            background: #fff; color: var(--brand); font-weight: 700; box-shadow: 0 1px 3px rgb(0 0 0 / .15); border: 1px solid var(--line); }
        .dark .bem-drive__new { background: rgb(255 255 255 / .08); color: #fff; }
        .bem-drive__new:disabled { opacity: .5; cursor: not-allowed; }
        .bem-drive__new-icon { width: 1.4rem; height: 1.4rem; }
        .bem-drive__nav { display: grid; gap: .15rem; }
        .bem-drive__nav button { display: flex; align-items: center; gap: .75rem; padding: .55rem .9rem; border-radius: 999px; text-align: left; }
        .bem-drive__nav button:hover { background: var(--soft); }
        .bem-drive__nav button.is-active { background: rgb(35 35 97 / .12); color: var(--brand); font-weight: 700; }
        .dark .bem-drive__nav button.is-active { background: rgb(255 219 26 / .15); color: var(--accent); }
        .bem-drive__nav-icon { width: 1.15rem; height: 1.15rem; flex-shrink: 0; }
        .bem-drive__storage { padding: 0 .9rem; display: grid; gap: .35rem; font-size: .8rem; color: rgb(100 116 139); }
        .bem-drive__storage-bar { height: .35rem; border-radius: 999px; background: var(--soft); overflow: hidden; }
        .bem-drive__storage-bar span { display: block; height: 100%; background: var(--brand); border-radius: 999px; }
        .bem-drive__storage-bar span.is-full { background: #DC2626; }
        .bem-drive__main { min-width: 0; display: flex; flex-direction: column; gap: .9rem; }
        .bem-drive__toolbar { display: flex; gap: .75rem; align-items: center; }
        .bem-drive__search { flex: 1; display: flex; align-items: center; gap: .6rem; padding: .6rem 1rem; border-radius: 999px; background: var(--soft); }
        .bem-drive__search input { flex: 1; background: transparent; border: 0; outline: 0; padding: 0; font-size: .9rem; box-shadow: none; }
        .bem-drive__toggle { display: inline-flex; border: 1px solid var(--line); border-radius: 999px; overflow: hidden; }
        .bem-drive__toggle button { padding: .5rem .75rem; }
        .bem-drive__toggle button.is-active { background: rgb(35 35 97 / .12); color: var(--brand); }
        .dark .bem-drive__toggle button.is-active { color: var(--accent); }
        .bem-drive__crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; color: rgb(100 116 139); }
        .bem-drive__crumbs button:hover { text-decoration: underline; }
        .bem-drive__crumbs .is-current { color: inherit; font-weight: 700; color: var(--brand); }
        .dark .bem-drive__crumbs .is-current { color: #fff; }
        .bem-drive__head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; align-items: center; }
        .bem-drive__head h2 { font-size: 1.35rem; font-weight: 800; }
        .bem-drive__head-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .bem-drive__badge { font-size: .75rem; padding: .2rem .6rem; border-radius: 999px; background: var(--soft); }
        .bem-drive__hint { font-size: .8rem; color: rgb(100 116 139); }
        .bem-drive__group { font-weight: 700; margin-top: .25rem; }
        .bem-drive__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .9rem; }
        .bem-drive__card { position: relative; border: 1px solid var(--line); border-radius: .9rem; background: var(--soft); overflow: hidden; }
        .bem-drive__card:hover { box-shadow: 0 2px 8px rgb(0 0 0 / .12); }
        .bem-drive__card-body { display: flex; flex-direction: column; width: 100%; text-align: left; }
        .bem-drive__thumb { --kind: var(--brand); aspect-ratio: 4 / 3; display: grid; place-items: center; background: #fff; overflow: hidden; }
        .dark .bem-drive__thumb { background: rgb(0 0 0 / .25); }
        .bem-drive__thumb img { width: 100%; height: 100%; object-fit: cover; }
        .bem-drive__thumb-icon { width: 3rem; height: 3rem; color: var(--kind); }
        .bem-drive__card-text { display: grid; gap: .1rem; padding: .6rem 2.2rem .7rem .75rem; }
        .bem-drive__name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bem-drive__meta { font-size: .75rem; color: rgb(100 116 139); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bem-drive__meta.is-error { color: #DC2626; }
        .bem-drive__menu { position: absolute; right: .3rem; bottom: .45rem; display: flex; align-items: center; }
        .bem-drive__row .bem-drive__menu { position: static; }
        .bem-drive__icon-btn { padding: .3rem; border-radius: 999px; color: rgb(100 116 139); }
        .bem-drive__icon-btn:hover { background: rgb(148 163 184 / .25); }
        .bem-drive__icon-btn.is-starred { color: #D97706; }
        .bem-drive__list { display: grid; border: 1px solid var(--line); border-radius: .9rem; overflow: hidden; }
        .bem-drive__row { display: flex; align-items: center; border-bottom: 1px solid var(--line); padding-right: .4rem; }
        .bem-drive__row:last-child { border-bottom: 0; }
        .bem-drive__row:hover { background: var(--soft); }
        .bem-drive__row-body { flex: 1; min-width: 0; display: grid; grid-template-columns: 1.5rem minmax(0, 3fr) minmax(0, 1.4fr) minmax(0, 1.2fr) minmax(0, 1.4fr);
            gap: .75rem; align-items: center; padding: .6rem .9rem; text-align: left; }
        .bem-drive__row-icon { width: 1.3rem; height: 1.3rem; color: var(--brand); }
        .bem-drive__empty { display: grid; justify-items: center; gap: .35rem; padding: 3rem 1rem; border: 2px dashed var(--line); border-radius: 1rem; text-align: center; color: rgb(100 116 139); }
        .bem-drive__empty-icon { width: 2.75rem; height: 2.75rem; color: rgb(148 163 184); }
        .bem-drive__empty-title { font-weight: 700; color: inherit; }
        .bem-drive__drop { position: absolute; inset: 0; z-index: 20; display: grid; place-items: center; text-align: center;
            background: rgb(35 35 97 / .12); border: 3px dashed var(--brand); border-radius: 1rem; backdrop-filter: blur(2px); }
        .bem-drive__queue { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 40; width: min(22rem, calc(100vw - 2.5rem));
            max-height: 50vh; overflow: auto; padding: .9rem; border-radius: .9rem; background: #fff; box-shadow: 0 8px 30px rgb(0 0 0 / .2); display: grid; gap: .6rem; }
        .dark .bem-drive__queue { background: rgb(24 24 27); }
        .bem-drive__queue-head { display: flex; justify-content: space-between; }
        .bem-drive__queue-item { display: grid; grid-template-columns: 1fr auto; gap: .2rem .6rem; }
        .bem-drive__queue-bar { grid-column: 1 / -1; height: .25rem; background: var(--soft); border-radius: 999px; overflow: hidden; }
        .bem-drive__queue-bar span { display: block; height: 100%; background: var(--brand); transition: width .2s; }
        .bem-drive__preview { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 1rem; background: rgb(0 0 0 / .7); }
        .bem-drive__preview-box { width: min(64rem, 100%); max-height: 92vh; display: flex; flex-direction: column; gap: .6rem; }
        .bem-drive__preview-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; color: #fff; }
        .bem-drive__preview-btn { padding: .4rem .9rem; border-radius: .6rem; background: rgb(255 255 255 / .15); color: #fff; margin-left: .35rem; }
        .bem-drive__preview-img { max-height: 82vh; max-width: 100%; margin: 0 auto; border-radius: .5rem; background: #fff; }
        .bem-drive__preview-frame { width: 100%; height: 82vh; border: 0; border-radius: .5rem; background: #fff; }
        @media (max-width: 900px) {
            .bem-drive { grid-template-columns: 1fr; }
            .bem-drive__nav { grid-auto-flow: column; overflow-x: auto; }
            .bem-drive__nav button span { white-space: nowrap; }
            .bem-drive__row-body { grid-template-columns: 1.5rem minmax(0, 1fr) auto; }
            .bem-drive__row-body > .bem-drive__meta:nth-of-type(n+2) { display: none; }
        }
    </style>
</x-filament-panels::page>
