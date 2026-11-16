<x-filament-panels::page>
    <div class="bem-chat {{ $current ? 'has-open' : '' }}"
        @if ($polling) wire:poll.5s="poll" @endif
        x-data="{
            scrollDown() { this.$nextTick(() => { const box = this.$refs.messages; if (box) box.scrollTop = box.scrollHeight }) },
            async pickFile(event) {
                const file = event.target.files[0]
                event.target.value = ''
                if (! file) return
                let upload = file
                // Gambar besar dari HP diperkecil di browser (≤1600px, JPEG) sebelum dikirim; server lalu mengompres ke WebP.
                if (/^image\/(jpeg|png|webp)$/.test(file.type) && 'createImageBitmap' in window) {
                    try {
                        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
                        const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height))
                        if (scale < 1 || file.size > 1.5 * 1024 * 1024) {
                            const canvas = document.createElement('canvas')
                            canvas.width = Math.round(bitmap.width * scale); canvas.height = Math.round(bitmap.height * scale)
                            const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height)
                            ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
                            const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.88))
                            if (blob) upload = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' })
                        }
                        bitmap.close?.()
                    } catch (e) {}
                }
                this.uploading = true
                $wire.upload('attachment', upload, () => { this.uploading = false }, () => { this.uploading = false }, (e) => { this.progress = e.detail.progress })
            },
            uploading: false,
            progress: 0,
        }"
        x-init="scrollDown(); window.dispatchEvent(new CustomEvent('chat-ready'))"
        x-on:chat-sent.window="scrollDown()"
        x-on:chat-changed.window="if ($event.detail && $event.detail.conversation_id === @js($current['id'] ?? null)) { setTimeout(() => scrollDown(), 350) }"
    >
        {{-- ============ Daftar percakapan ============ --}}
        <aside class="bem-chat__list">
            <div class="bem-chat__list-head">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari percakapan" aria-label="Cari percakapan" class="bem-chat__search">
                <div class="bem-chat__list-actions">
                    {{ $this->newChatAction }}
                    {{ $this->newGroupAction }}
                    {{ $this->allGroupsAction }}
                </div>
            </div>
            <div class="bem-chat__items">
                @forelse ($conversations as $c)
                    <button type="button" wire:key="conv-{{ $c['id'] }}" wire:click="open({{ $c['id'] }})"
                        @class(['bem-chat__item', 'is-active' => ($current['id'] ?? null) === $c['id']])>
                        <span @class(['bem-chat__avatar', 'is-group' => $c['type'] !== \App\Enums\ConversationType::Personal])>{{ $c['initial'] }}</span>
                        <span class="bem-chat__item-text">
                            <span class="bem-chat__item-top">
                                <strong>{{ $c['name'] }}</strong>
                                <small>{{ $c['time'] }}</small>
                            </span>
                            <span class="bem-chat__item-bottom">
                                <span>{{ $c['preview'] }}</span>
                                @if ($c['unread'] > 0)<span class="bem-chat__badge">{{ $c['unread'] > 99 ? '99+' : $c['unread'] }}</span>@endif
                            </span>
                        </span>
                    </button>
                @empty
                    <p class="bem-chat__empty">Belum ada percakapan. Mulai dengan tombol <strong>Chat baru</strong>.</p>
                @endforelse
            </div>
        </aside>

        {{-- ============ Percakapan ============ --}}
        <section class="bem-chat__main">
            @if (! $current)
                <div class="bem-chat__placeholder">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="bem-chat__placeholder-icon" />
                    <p>Pilih percakapan di kiri atau mulai chat baru.</p>
                    <small>{{ $polling ? 'Mode hemat: pesan diperbarui tiap 5 detik.' : 'Tersambung real-time.' }}</small>
                </div>
            @else
                <header class="bem-chat__head">
                    <button type="button" class="bem-chat__back" wire:click="close" aria-label="Kembali ke daftar">‹</button>
                    <div class="bem-chat__title">
                        <strong>{{ $current['name'] }}</strong>
                        <small>
                            {{ $current['type']->label() }}
                            @if ($current['type'] !== \App\Enums\ConversationType::Personal) · {{ $current['members']->count() }} anggota @endif
                            @if ($current['muted']) · 🔕 di-mute @endif
                        </small>
                    </div>
                    <div class="bem-chat__head-actions">
                        <input type="search" wire:model.live.debounce.400ms="messageSearch" placeholder="Cari pesan" aria-label="Cari pesan di percakapan ini" class="bem-chat__search bem-chat__search--small">
                        <x-filament::icon-button :icon="$current['muted'] ? 'heroicon-o-bell-slash' : 'heroicon-o-bell'" color="gray" wire:click="toggleMute" :label="$current['muted'] ? 'Nyalakan notifikasi' : 'Mute percakapan'" />
                        @if ($current['type'] !== \App\Enums\ConversationType::Personal)
                            <x-filament::dropdown placement="bottom-end">
                                <x-slot name="trigger">
                                    <x-filament::icon-button icon="heroicon-o-users" color="gray" label="Anggota" />
                                </x-slot>
                                <div class="bem-chat__members">
                                    @foreach ($current['members'] as $member)
                                        <div wire:key="mem-{{ $member['id'] }}">
                                            <span>{{ $member['name'] }} @if ($member['admin'])<small>(admin)</small>@endif @if ($member['inactive'])<small>(nonaktif)</small>@endif</span>
                                            @if ($current['canManage'] && $member['id'] !== auth()->id())
                                                <x-filament::link tag="button" size="sm" color="danger" wire:click="removeMember({{ $member['id'] }})" wire:confirm="Keluarkan {{ $member['name'] }} dari grup?">Keluarkan</x-filament::link>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </x-filament::dropdown>
                        @endif
                        {{ $this->manageGroupAction }}
                        {{ $this->leaveGroupAction }}
                        {{ $this->dissolveGroupAction }}
                    </div>
                </header>

                <div class="bem-chat__messages" x-ref="messages">
                    @if ($current['hasMore'])
                        <button type="button" class="bem-chat__more" wire:click="loadMore">Muat pesan sebelumnya</button>
                    @endif
                    @if (trim($messageSearch) !== '')
                        <p class="bem-chat__empty">Hasil pencarian di 200 pesan terakhir: {{ $current['messages']->count() }} pesan.</p>
                    @endif
                    @forelse ($current['messages'] as $m)
                        <div wire:key="msg-{{ $m['id'] }}" @class(['bem-chat__msg', 'is-mine' => $m['mine']])>
                            <div class="bem-chat__bubble">
                                @if (! $m['mine'] && $current['type'] !== \App\Enums\ConversationType::Personal)
                                    <span class="bem-chat__sender">{{ $m['sender'] }}</span>
                                @endif
                                @if ($m['deleted'])
                                    <em class="bem-chat__deleted">Pesan dihapus</em>
                                @else
                                    @if ($m['reply'])
                                        <span class="bem-chat__quote"><strong>{{ $m['reply']['sender'] }}</strong> {{ $m['reply']['preview'] }}</span>
                                    @endif
                                    @if ($m['attachment'])
                                        @if ($m['attachment']['image'])
                                            <a href="{{ $m['attachment']['url'] }}" target="_blank" rel="noopener"><img src="{{ $m['attachment']['url'] }}" alt="{{ $m['attachment']['name'] }}" loading="lazy" class="bem-chat__image"></a>
                                        @else
                                            <a href="{{ $m['attachment']['url'] }}" class="bem-chat__file">📎 {{ $m['attachment']['name'] }} <small>{{ $m['attachment']['size'] }}</small></a>
                                        @endif
                                    @elseif ($m['expired'])
                                        <em class="bem-chat__deleted">Lampiran sudah kedaluwarsa</em>
                                    @endif
                                    @if (filled($m['body']))
                                        <span class="bem-chat__text">{{ $m['body'] }}</span>
                                    @endif
                                @endif
                                <span class="bem-chat__meta">
                                    {{ $m['time'] }} @if ($m['read']) · {{ $m['read'] }} @endif
                                    @unless ($m['deleted'])
                                        @if ($canSend)<button type="button" wire:click="replyTo({{ $m['id'] }})">Balas</button>@endif
                                        @if ($m['mine'])<button type="button" wire:click="deleteMessage({{ $m['id'] }})" wire:confirm="Hapus pesan ini? Isi & lampirannya dihapus permanen.">Hapus</button>@endif
                                    @endunless
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="bem-chat__empty">Belum ada pesan. Sapa duluan! 👋</p>
                    @endforelse
                </div>

                @if ($canSend)
                    <form class="bem-chat__composer" wire:submit="send">
                        @if ($replyTo)
                            <div class="bem-chat__replying">
                                <span>Membalas <strong>{{ $replyTo->sender?->name }}</strong>: {{ $replyTo->preview(60) }}</span>
                                <button type="button" wire:click="cancelReply" aria-label="Batal membalas">✕</button>
                            </div>
                        @endif
                        @if ($attachment)
                            <div class="bem-chat__replying">
                                <span>📎 {{ $attachment->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removeAttachment" aria-label="Hapus lampiran">✕</button>
                            </div>
                        @endif
                        <div class="bem-chat__uploading" x-show="uploading" x-cloak>Mengunggah lampiran… <span x-text="progress + '%'"></span></div>
                        @error('body') <p class="bem-chat__error">{{ $message }}</p> @enderror
                        @error('attachment') <p class="bem-chat__error">{{ $message }}</p> @enderror
                        <div class="bem-chat__compose-row">
                            <label class="bem-chat__attach" title="Lampirkan gambar atau berkas">
                                <x-filament::icon icon="heroicon-o-paper-clip" class="bem-chat__attach-icon" />
                                <input type="file" class="bem-chat__hidden" accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip" x-on:change="pickFile($event)">
                            </label>
                            <textarea wire:model="body" rows="1" maxlength="{{ \App\Services\Chat\ChatService::MAX_BODY }}" placeholder="Tulis pesan…" aria-label="Tulis pesan"
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" x-bind:disabled="uploading" wire:loading.attr="disabled" wire:target="send,attachment">Kirim</x-filament::button>
                        </div>
                    </form>
                @else
                    <p class="bem-chat__readonly">Anda tidak bisa mengirim pesan di percakapan ini (akun lawan bicara nonaktif).</p>
                @endif
            @endif
        </section>
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .bem-chat { --line: rgb(148 163 184 / .35); --soft: rgb(241 245 249); --brand: #232361; display: grid; grid-template-columns: 20rem 1fr;
            height: calc(100vh - 9rem); min-height: 28rem; border: 1px solid var(--line); border-radius: 1rem; overflow: hidden; background: #fff; font-size: .9rem; }
        .dark .bem-chat { --soft: rgb(255 255 255 / .05); background: rgb(24 24 27); }
        .bem-chat__list { display: flex; flex-direction: column; border-right: 1px solid var(--line); min-height: 0; }
        .bem-chat__list-head { padding: .75rem; display: grid; gap: .5rem; border-bottom: 1px solid var(--line); }
        .bem-chat__list-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
        .bem-chat__search { width: 100%; padding: .5rem .8rem; border-radius: 999px; border: 1px solid var(--line); background: var(--soft); font-size: .85rem; }
        .bem-chat__search--small { width: 10rem; }
        .bem-chat__items { overflow-y: auto; flex: 1; }
        .bem-chat__item { display: flex; gap: .65rem; width: 100%; padding: .65rem .75rem; text-align: left; border-bottom: 1px solid var(--line); }
        .bem-chat__item:hover, .bem-chat__item.is-active { background: var(--soft); }
        .bem-chat__avatar { width: 2.4rem; height: 2.4rem; flex-shrink: 0; border-radius: 999px; display: grid; place-items: center; background: var(--brand); color: #fff; font-weight: 700; }
        .bem-chat__avatar.is-group { background: #FFDB1A; color: var(--brand); border-radius: .7rem; }
        .bem-chat__item-text { min-width: 0; flex: 1; display: grid; gap: .1rem; }
        .bem-chat__item-top, .bem-chat__item-bottom { display: flex; justify-content: space-between; gap: .5rem; }
        .bem-chat__item-top strong, .bem-chat__item-bottom span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bem-chat__item-top small, .bem-chat__item-bottom span:first-child { color: rgb(100 116 139); font-size: .78rem; }
        .bem-chat__badge { flex-shrink: 0; min-width: 1.3rem; padding: 0 .35rem; border-radius: 999px; background: #DC2626; color: #fff; font-size: .72rem; font-weight: 700; text-align: center; }
        .bem-chat__main { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
        .bem-chat__placeholder { margin: auto; text-align: center; color: rgb(100 116 139); display: grid; gap: .4rem; justify-items: center; }
        .bem-chat__placeholder-icon { width: 3rem; height: 3rem; }
        .bem-chat__head { display: flex; align-items: center; gap: .6rem; padding: .6rem .9rem; border-bottom: 1px solid var(--line); }
        .bem-chat__back { display: none; font-size: 1.6rem; line-height: 1; padding: 0 .3rem; }
        .bem-chat__title { display: grid; min-width: 0; flex: 1; }
        .bem-chat__title strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bem-chat__title small { color: rgb(100 116 139); font-size: .78rem; }
        .bem-chat__head-actions { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; justify-content: flex-end; }
        .bem-chat__members { padding: .5rem .75rem; display: grid; gap: .4rem; max-height: 18rem; overflow-y: auto; min-width: 16rem; font-size: .85rem; }
        .bem-chat__members > div { display: flex; justify-content: space-between; gap: .5rem; }
        .bem-chat__members small { color: rgb(100 116 139); }
        .bem-chat__messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .45rem; background: var(--soft); }
        .bem-chat__more { align-self: center; font-size: .8rem; padding: .3rem .8rem; border-radius: 999px; border: 1px solid var(--line); background: #fff; }
        .dark .bem-chat__more { background: transparent; }
        .bem-chat__msg { display: flex; }
        .bem-chat__msg.is-mine { justify-content: flex-end; }
        .bem-chat__bubble { max-width: min(36rem, 80%); display: grid; gap: .25rem; padding: .5rem .75rem; border-radius: .9rem; background: #fff; box-shadow: 0 1px 1px rgb(0 0 0 / .06); }
        .dark .bem-chat__bubble { background: rgb(39 39 42); }
        .is-mine .bem-chat__bubble { background: #E6E6F4; }
        .dark .is-mine .bem-chat__bubble { background: rgb(35 35 97 / .7); }
        .bem-chat__sender { font-weight: 700; font-size: .78rem; color: var(--brand); }
        .dark .bem-chat__sender { color: #FFDB1A; }
        .bem-chat__text { white-space: pre-wrap; overflow-wrap: anywhere; }
        .bem-chat__deleted { color: rgb(100 116 139); font-size: .85rem; }
        .bem-chat__quote { padding: .3rem .5rem; border-left: 3px solid #FFDB1A; background: rgb(148 163 184 / .15); border-radius: .4rem; font-size: .8rem; overflow: hidden; text-overflow: ellipsis; }
        .bem-chat__image { max-width: 16rem; max-height: 16rem; border-radius: .6rem; }
        .bem-chat__file { text-decoration: underline; overflow-wrap: anywhere; }
        .bem-chat__file small { color: rgb(100 116 139); text-decoration: none; }
        .bem-chat__meta { font-size: .7rem; color: rgb(100 116 139); display: flex; gap: .5rem; justify-content: flex-end; flex-wrap: wrap; }
        .bem-chat__meta button { text-decoration: underline; }
        .bem-chat__empty { text-align: center; color: rgb(100 116 139); padding: 1.5rem .5rem; font-size: .85rem; }
        .bem-chat__composer { border-top: 1px solid var(--line); padding: .6rem .75rem; display: grid; gap: .4rem; }
        .bem-chat__replying { display: flex; justify-content: space-between; gap: .5rem; padding: .35rem .6rem; border-radius: .5rem; background: var(--soft); font-size: .8rem; }
        .bem-chat__uploading { font-size: .8rem; color: rgb(100 116 139); }
        .bem-chat__error { color: #DC2626; font-size: .8rem; }
        .bem-chat__compose-row { display: flex; align-items: flex-end; gap: .5rem; }
        .bem-chat__compose-row textarea { flex: 1; resize: none; max-height: 8rem; min-height: 2.5rem; padding: .55rem .8rem; border-radius: 1rem; border: 1px solid var(--line); background: transparent; field-sizing: content; }
        .bem-chat__attach { cursor: pointer; padding: .55rem; border-radius: 999px; }
        .bem-chat__attach:hover { background: var(--soft); }
        .bem-chat__attach-icon { width: 1.3rem; height: 1.3rem; }
        .bem-chat__hidden { display: none; }
        .bem-chat__readonly { padding: .8rem; text-align: center; color: rgb(100 116 139); border-top: 1px solid var(--line); font-size: .85rem; }
        @media (max-width: 900px) {
            .bem-chat { grid-template-columns: 1fr; height: calc(100vh - 7rem); }
            .bem-chat.has-open .bem-chat__list { display: none; }
            .bem-chat:not(.has-open) .bem-chat__main { display: none; }
            .bem-chat__back { display: block; }
            .bem-chat__search--small { width: 7rem; }
        }
    </style>
</x-filament-panels::page>
