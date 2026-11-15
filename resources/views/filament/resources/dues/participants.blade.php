@php
    use App\Support\Format;
@endphp
<div style="max-height: 60vh; overflow: auto; border: 1px solid rgb(148 163 184 / .35); border-radius: .5rem; font-size: .875rem;">
    @if ($rows->isEmpty())
        <p style="padding: 1rem;">Belum ada peserta.</p>
    @else
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="position: sticky; top: 0; background: rgb(148 163 184 / .15);">
                <tr style="text-align: left;">
                    <th style="padding: .5rem;">Pengurus</th>
                    <th style="padding: .5rem; text-align: right;">Tagihan berjalan</th>
                    <th style="padding: .5rem; text-align: right;">Dibayar</th>
                    <th style="padding: .5rem; text-align: right;">Tunggakan</th>
                    <th style="padding: .5rem; text-align: right;">Sisa target</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr style="border-top: 1px solid rgb(148 163 184 / .25);">
                        <td style="padding: .5rem;">
                            {{ $row['user']->name }}
                            <span style="display: block; font-size: .75rem; opacity: .7;">{{ $row['user']->division?->name ?? 'Tanpa divisi' }}</span>
                        </td>
                        <td style="padding: .5rem; text-align: right;">{{ Format::rupiah($row['billed']) }}</td>
                        <td style="padding: .5rem; text-align: right;">{{ Format::rupiah($row['paid']) }}</td>
                        <td style="padding: .5rem; text-align: right; {{ $row['arrears'] > 0 ? 'color: #b91c1c; font-weight: 700;' : '' }}">{{ Format::rupiah($row['arrears']) }}</td>
                        <td style="padding: .5rem; text-align: right;">
                            @if ($row['remaining'] === 0)
                                <x-filament::badge color="success" size="sm">Lunas</x-filament::badge>
                            @else
                                {{ Format::rupiah($row['remaining']) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
