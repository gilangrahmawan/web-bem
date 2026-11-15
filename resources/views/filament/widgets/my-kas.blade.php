@php
    use App\Notifications\KasPaidNotification;
    use App\Support\Format;

    $due = $summary ? count($summary->dueWeeks) : 0;
    $paidDue = $summary ? $summary->paidDueWeeksCount() : 0;
    $percent = $due > 0 ? (int) round($paidDue / $due * 100) : 100;
@endphp
<x-filament-widgets::widget>
    <x-filament::section heading="Kas Saya" icon="heroicon-o-banknotes">
        @if ($problem)
            <p style="font-size: .875rem; opacity: .8;">
                <strong>{{ $problem }}</strong> Belum ada tagihan kas untuk Anda.
            </p>
        @elseif (! $summary || $summary->firstWeek === null)
            <p style="font-size: .875rem; opacity: .8;">Kewajiban kas Anda belum dimulai pada periode ini.</p>
        @else
            <div style="display: grid; gap: 1rem; font-size: .875rem;">
                <div style="display: flex; flex-wrap: wrap; gap: 1.5rem;">
                    <div>
                        <div style="font-size: .75rem; opacity: .7;">Minggu dibayar</div>
                        <div style="font-size: 1.25rem; font-weight: 800;">{{ $paidDue }}/{{ $due }}</div>
                    </div>
                    <div>
                        <div style="font-size: .75rem; opacity: .7;">Sisa tunggakan</div>
                        <div style="font-size: 1.25rem; font-weight: 800; {{ $summary->arrears > 0 ? 'color: #b91c1c;' : 'color: #15803d;' }}">
                            {{ $summary->arrears > 0 ? Format::rupiah($summary->arrears) : 'Lunas' }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size: .75rem; opacity: .7;">Total dibayar</div>
                        <div style="font-size: 1.25rem; font-weight: 800;">{{ Format::rupiah($summary->paidTotal()) }}</div>
                    </div>
                </div>

                <div>
                    <div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}"
                        aria-label="Progres kas: {{ $paidDue }} dari {{ $due }} minggu jatuh tempo sudah dibayar"
                        style="height: .75rem; border-radius: 999px; background: rgb(148 163 184 / .3); overflow: hidden;">
                        <div style="height: 100%; width: {{ $percent }}%; background: {{ $summary->arrears > 0 ? '#FFDB1A' : '#232361' }};"></div>
                    </div>
                    <div style="margin-top: .35rem; font-size: .75rem; opacity: .75;">
                        {{ $percent }}% minggu jatuh tempo sudah dibayar · {{ Format::rupiah($amount) }}/minggu
                        @if ($firstWeek) · wajib sejak {{ Format::date($firstWeek->start) }} @endif
                        @if ($summary->unpaidWeeks)
                            · belum dibayar: minggu ke-{{ KasPaidNotification::weekList($summary->unpaidWeeks) }}
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($dues)
            <div style="margin-top: 1rem; font-size: .875rem;">
                <div style="font-weight: 700; margin-bottom: .35rem;">Iuran tambahan belum lunas</div>
                <ul style="display: grid; gap: .35rem;">
                    @foreach ($dues as $row)
                        <li style="display: flex; justify-content: space-between; gap: 1rem;">
                            <span>{{ $row['due']->name }}</span>
                            <span>
                                @if ($row['arrears'] > 0)
                                    <strong style="color: #b91c1c;">{{ Format::rupiah($row['arrears']) }}</strong> jatuh tempo ·
                                @endif
                                sisa {{ Format::rupiah($row['remaining']) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
