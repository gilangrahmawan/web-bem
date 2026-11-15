@php
    use App\Support\Format;
@endphp
<x-filament-panels::page>
    <div class="bem-kas">
        @if ($problem)
            <div class="bem-kas__notice" role="status">
                <strong>{{ $problem }}</strong>
                <span>
                    Selama belum diatur, tidak ada minggu jatuh tempo dan tidak ada tunggakan.
                    @if ($canManage)
                        Atur lewat tombol <em>Pengaturan kas</em> di kanan atas.
                    @else
                        Hubungi Bendahara atau Super Admin.
                    @endif
                </span>
            </div>
        @else
            <div class="bem-kas__stats">
                <div><span>Nominal</span><strong>{{ Format::rupiah($amount) }}/minggu</strong></div>
                <div><span>Mulai kas</span><strong>{{ Format::date($startDate) }}</strong></div>
                <div><span>Minggu berjalan</span><strong>{{ $currentWeek ? 'Ke-'.$currentWeek : 'Belum mulai' }}</strong></div>
                <div><span>Terkumpul</span><strong>{{ Format::rupiah($totals['collected']) }}</strong></div>
                <div class="{{ $totals['arrears'] > 0 ? 'is-danger' : '' }}"><span>Tunggakan</span><strong>{{ Format::rupiah($totals['arrears']) }}</strong></div>
                <div><span>Lunas</span><strong>{{ $totals['clear'] }}/{{ $totals['count'] }} pengurus</strong></div>
            </div>

            <div class="bem-kas__filters">
                <label>
                    <span>Bulan</span>
                    <select wire:model.live="month">
                        <option value="all">Semua minggu</option>
                        @foreach ($months as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Divisi</span>
                    <select wire:model.live="divisionId">
                        <option value="">Semua divisi</option>
                        @foreach ($divisions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="bem-kas__search">
                    <span>Cari nama</span>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Nama pengurus…">
                </label>
            </div>

            <div class="bem-kas__legend">
                <span><i class="bem-kas__cell is-paid">✓</i> Lunas</span>
                <span><i class="bem-kas__cell is-overdue">!</i> Menunggak</span>
                <span><i class="bem-kas__cell"></i> Belum jatuh tempo</span>
                <span><i class="bem-kas__cell is-na">—</i> Belum wajib</span>
                @if ($canManage)
                    <span class="bem-kas__hint">Klik sel untuk mencatat; klik sel lunas untuk membatalkan.</span>
                @endif
            </div>

            @if ($summaries->isEmpty())
                <x-filament::empty-state heading="Tidak ada pengurus" description="Belum ada pengurus wajib kas yang cocok dengan filter ini." icon="heroicon-o-users" />
            @else
                <div class="bem-kas__scroll">
                    <table class="bem-kas__table">
                        <thead>
                            <tr>
                                <th class="is-sticky" scope="col">Pengurus</th>
                                @foreach ($weeks as $week)
                                    <th scope="col" title="{{ $week->rangeLabel() }}" class="{{ $week->number === $currentWeek ? 'is-current' : '' }}">
                                        <span>M{{ $week->number }}</span>
                                        <small>{{ $week->shortLabel() }}</small>
                                    </th>
                                @endforeach
                                <th scope="col">Dibayar</th>
                                <th scope="col">Tunggakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summaries as $summary)
                                @php $member = $summary->user; @endphp
                                <tr wire:key="kas-row-{{ $member->id }}" class="{{ $summary->isClear() ? '' : 'is-arrears' }}">
                                    <th class="is-sticky" scope="row">
                                        <span class="bem-kas__name">{{ $member->name }}</span>
                                        <small>{{ $member->division?->name ?? 'Tanpa divisi' }}@if ($member->kas_starts_on) · mulai {{ Format::date($member->kas_starts_on) }}@endif</small>
                                        @if ($canManage)
                                            <span class="bem-kas__row-actions">
                                                <button type="button" wire:click="mountAction('recordWeeks', { user: {{ $member->id }} })">+ Catat minggu</button>
                                                <button type="button" wire:click="mountAction('memberStart', { user: {{ $member->id }} })">Mulai wajib</button>
                                            </span>
                                        @endif
                                    </th>
                                    @foreach ($weeks as $week)
                                        @php
                                            $n = $week->number;
                                            $paid = $summary->isPaid($n);
                                            $required = $summary->isRequired($n);
                                            $overdue = $summary->isOverdue($n);
                                            $state = $paid ? 'is-paid' : (! $required ? 'is-na' : ($overdue ? 'is-overdue' : ''));
                                            $label = $paid ? '✓' : (! $required ? '—' : ($overdue ? '!' : ''));
                                            $title = $member->name.' · minggu ke-'.$n.' ('.$week->rangeLabel().'): '
                                                .($paid ? 'lunas '.Format::rupiah($summary->payments[$n]) : (! $required ? 'belum wajib' : ($overdue ? 'menunggak' : 'belum jatuh tempo')));
                                        @endphp
                                        <td>
                                            @if ($canManage && ($paid || $required))
                                                <button type="button"
                                                    class="bem-kas__cell {{ $state }}"
                                                    title="{{ $title }}"
                                                    aria-label="{{ $title }}"
                                                    wire:click="toggle({{ $member->id }}, {{ $n }})"
                                                    wire:loading.attr="disabled"
                                                    @if ($paid) wire:confirm="Batalkan pembayaran kas minggu ke-{{ $n }} {{ $member->name }}?" @endif
                                                >{{ $label }}</button>
                                            @else
                                                <span class="bem-kas__cell {{ $state }}" title="{{ $title }}" aria-label="{{ $title }}">{{ $label }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="is-num">{{ Format::rupiah($summary->paidTotal()) }}</td>
                                    <td class="is-num {{ $summary->arrears > 0 ? 'is-danger' : '' }}">
                                        {{ Format::rupiah($summary->arrears) }}
                                        @if ($summary->unpaidWeeks)
                                            <small>{{ count($summary->unpaidWeeks) }} minggu</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    <style>
        .bem-kas { display: grid; gap: 1rem; font-size: .875rem; }
        .bem-kas__notice { display: grid; gap: .25rem; padding: 1rem 1.25rem; border-radius: .75rem; border: 1px solid rgb(245 158 11 / .5); background: rgb(254 243 199 / .6); color: #78350f; }
        .dark .bem-kas__notice { background: rgb(120 53 15 / .25); color: #fde68a; }
        .bem-kas__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .75rem; }
        .bem-kas__stats > div { display: grid; gap: .15rem; padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgb(148 163 184 / .35); }
        .bem-kas__stats span, .bem-kas__filters span { font-size: .75rem; font-weight: 600; opacity: .7; }
        .bem-kas__stats strong { font-size: 1rem; }
        .bem-kas__stats .is-danger strong, td.is-danger { color: #b91c1c; }
        .dark .bem-kas__stats .is-danger strong, .dark td.is-danger { color: #fca5a5; }
        .bem-kas__filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
        .bem-kas__filters label { display: grid; gap: .25rem; }
        .bem-kas__filters select, .bem-kas__filters input { border-radius: .5rem; border: 1px solid rgb(148 163 184 / .5); padding: .45rem .6rem; background: transparent; color: inherit; min-width: 10rem; }
        .bem-kas__filters option { color: #0f172a; }
        .bem-kas__search { flex: 1 1 14rem; }
        .bem-kas__search input { width: 100%; }
        .bem-kas__legend { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; font-size: .8125rem; }
        .bem-kas__legend span { display: inline-flex; align-items: center; gap: .35rem; }
        .bem-kas__legend i { font-style: normal; }
        .bem-kas__hint { opacity: .7; }
        .bem-kas__scroll { overflow-x: auto; border: 1px solid rgb(148 163 184 / .35); border-radius: .75rem; }
        .bem-kas__table { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; }
        .bem-kas__table th, .bem-kas__table td { padding: .4rem .35rem; border-bottom: 1px solid rgb(148 163 184 / .2); text-align: center; white-space: nowrap; }
        .bem-kas__table thead th { font-size: .75rem; font-weight: 700; position: sticky; top: 0; background: rgb(248 250 252); z-index: 1; }
        .dark .bem-kas__table thead th { background: rgb(24 24 27); }
        .bem-kas__table thead th small, .bem-kas__table td small, .bem-kas__table th.is-sticky small { display: block; font-size: .6875rem; font-weight: 500; opacity: .7; }
        .bem-kas__table thead th.is-current { background: rgb(255 219 26 / .35); }
        .bem-kas__table .is-sticky { position: sticky; left: 0; z-index: 2; text-align: left; min-width: 13rem; max-width: 16rem; white-space: normal; background: white; box-shadow: 1px 0 0 rgb(148 163 184 / .35); }
        .dark .bem-kas__table .is-sticky { background: rgb(24 24 27); }
        .bem-kas__table thead .is-sticky { z-index: 3; }
        .bem-kas__table tr.is-arrears > .is-sticky { box-shadow: inset 3px 0 0 #dc2626, 1px 0 0 rgb(148 163 184 / .35); }
        .bem-kas__table tr.is-arrears > td { background: rgb(254 226 226 / .45); }
        .dark .bem-kas__table tr.is-arrears > td { background: rgb(127 29 29 / .2); }
        .bem-kas__name { font-weight: 600; }
        .bem-kas__row-actions { display: flex; gap: .35rem; margin-top: .25rem; }
        .bem-kas__row-actions button { font-size: .6875rem; font-weight: 600; padding: .1rem .45rem; border-radius: .375rem; border: 1px solid rgb(35 35 97 / .35); color: #232361; }
        .dark .bem-kas__row-actions button { color: #c7c7ff; border-color: rgb(199 199 255 / .35); }
        .bem-kas__table td.is-num { text-align: right; padding-inline: .75rem; font-variant-numeric: tabular-nums; }
        .bem-kas__cell { display: inline-flex; align-items: center; justify-content: center; width: 1.9rem; height: 1.9rem; border-radius: .4rem; border: 1px dashed rgb(148 163 184 / .6); font-weight: 800; font-size: .8125rem; }
        button.bem-kas__cell { cursor: pointer; }
        button.bem-kas__cell:hover { outline: 2px solid #232361; outline-offset: 1px; }
        .bem-kas__cell.is-paid { background: #15803d; border: 0; color: #fff; }
        .bem-kas__cell.is-overdue { background: #fee2e2; border: 1px solid #dc2626; color: #b91c1c; }
        .bem-kas__cell.is-na { border-color: transparent; opacity: .45; }
    </style>
</x-filament-panels::page>
