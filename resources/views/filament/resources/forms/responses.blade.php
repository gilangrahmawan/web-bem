{{-- Tab Jawaban pembuat form: ringkasan, statistik kuis, daftar responden, export. --}}
<div class="bem-fb__column" style="max-width: 60rem; margin: 0 auto; width: 100%;">
    <div class="bem-fb__card">
        <div class="bem-fb__inline" style="justify-content: space-between;">
            <strong style="font-size: 1.2rem;">{{ $responseCount }} jawaban</strong>
            @if ($canExport && $responseCount > 0)
                <div class="bem-fb__inline">
                    <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-down-tray" :href="route('panel.forms.export', [$form->getKey(), 'xlsx'])">Export Excel</x-filament::button>
                    <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-down-tray" :href="route('panel.forms.export', [$form->getKey(), 'csv'])">Export CSV</x-filament::button>
                </div>
            @endif
        </div>
        @if ($responseCount === 0)
            <p class="bem-fb__muted">Belum ada jawaban. Bagikan link atau QR form setelah diterbitkan.</p>
        @endif
    </div>

    @if ($stats)
        <div class="bem-fb__stats">
            <div class="bem-fb__stat"><span class="bem-fb__muted">Rata-rata</span><strong>{{ rtrim(rtrim(number_format($stats['avg'], 2, ',', '.'), '0'), ',') }}</strong><span class="bem-fb__muted">dari {{ rtrim(rtrim(number_format($stats['max_score'], 2, ',', '.'), '0'), ',') }}</span></div>
            <div class="bem-fb__stat"><span class="bem-fb__muted">Terendah</span><strong>{{ rtrim(rtrim(number_format($stats['min'], 2, ',', '.'), '0'), ',') }}</strong></div>
            <div class="bem-fb__stat"><span class="bem-fb__muted">Tertinggi</span><strong>{{ rtrim(rtrim(number_format($stats['max'], 2, ',', '.'), '0'), ',') }}</strong></div>
            @if ($stats['passed'] !== null)
                <div class="bem-fb__stat"><span class="bem-fb__muted">Lulus</span><strong>{{ $stats['passed'] }}</strong><span class="bem-fb__muted">dari {{ $responseCount }}</span></div>
            @endif
            <div class="bem-fb__stat"><span class="bem-fb__muted">Perlu dinilai</span><strong>{{ $stats['pending'] }}</strong></div>
        </div>
    @endif

    @if ($responseCount > 0)
        @foreach ($summary as $item)
            <div class="bem-fb__card">
                <div>
                    <strong>{{ $item['label'] }}</strong>
                    <div class="bem-fb__muted">{{ $item['count'] }} jawaban</div>
                </div>
                @if (isset($item['buckets']))
                    @php $total = max(1, array_sum($item['buckets'])); @endphp
                    <div class="bem-fb__bars" role="list">
                        @foreach ($item['buckets'] as $label => $count)
                            @php $pct = round($count / $total * 100); @endphp
                            <div class="bem-fb__bar" role="listitem" aria-label="{{ $label }}: {{ $count }} ({{ $pct }}%)">
                                <span>{{ $label }} @if (in_array($label, $item['correct'] ?? [], true))<span class="bem-fb__key">✓ benar</span>@endif</span>
                                <span class="bem-fb__bar-track"><span class="bem-fb__bar-fill {{ in_array($label, $item['correct'] ?? [], true) ? 'is-correct' : '' }}" style="display:block; width: {{ $pct }}%"></span></span>
                                <span>{{ $count }} ({{ $pct }}%)</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    @if ($item['samples'])
                        <ul class="bem-fb__samples">
                            @foreach ($item['samples'] as $sample)
                                <li>{{ \Illuminate\Support\Str::limit($sample, 300) }}</li>
                            @endforeach
                        </ul>
                        @if ($item['count'] > count($item['samples']))
                            <p class="bem-fb__muted">Menampilkan {{ count($item['samples']) }} jawaban terbaru. Lihat semua di daftar responden atau export.</p>
                        @endif
                    @else
                        <p class="bem-fb__muted">Belum ada jawaban.</p>
                    @endif
                @endif
            </div>
        @endforeach

        <div class="bem-fb__card">
            <div class="bem-fb__inline" style="justify-content: space-between;">
                <strong>Responden</strong>
                @if ($form->is_quiz)
                    <label class="bem-fb__check"><input type="checkbox" wire:model.live="onlyPending"> Hanya yang perlu dinilai</label>
                @endif
            </div>
            <div style="overflow-x: auto;">
                <table class="bem-fb__table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Waktu kirim</th>
                            <th>Responden</th>
                            @if ($form->is_quiz)<th>Nilai</th><th>Status</th>@endif
                            <th><span class="bem-fb__sr">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($responses as $response)
                            @php $no = $responseNumbers[$response->getKey()] ?? '-'; @endphp
                            <tr wire:key="r-{{ $response->getKey() }}">
                                <td>{{ $no }}</td>
                                <td>{{ $response->submitted_at->timezone(config('app.timezone'))->translatedFormat('j M Y, H:i') }}</td>
                                <td>{{ $response->respondent?->name ?? $response->respondent_email ?? 'Anonim' }}</td>
                                @if ($form->is_quiz)
                                    <td>{{ rtrim(rtrim(number_format((float) $response->score, 2, ',', '.'), '0'), ',') }} / {{ rtrim(rtrim(number_format((float) $response->max_score, 2, ',', '.'), '0'), ',') }}</td>
                                    <td>
                                        @if ($response->graded_at)
                                            <x-filament::badge color="success">Selesai dinilai</x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">Perlu dinilai</x-filament::badge>
                                        @endif
                                    </td>
                                @endif
                                <td><x-filament::link tag="button" wire:click="mountAction('viewResponse', { response: {{ $response->getKey() }}, no: {{ is_int($no) ? $no : 0 }} })">Lihat</x-filament::link></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="bem-fb__muted">Tidak ada jawaban yang perlu dinilai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($responseCount > 200)
                <p class="bem-fb__muted">Menampilkan 200 jawaban terbaru. Gunakan export untuk data lengkap.</p>
            @endif
        </div>
    @endif
</div>
