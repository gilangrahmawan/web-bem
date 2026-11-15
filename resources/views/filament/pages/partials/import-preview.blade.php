@php
    use App\Enums\EventCategory;
    use App\Services\CalendarImportService as Import;

    $badge = [
        Import::STATUS_VALID => ['success', 'Valid'],
        Import::STATUS_DUPLICATE => ['gray', 'Duplikat'],
        Import::STATUS_INVALID => ['danger', 'Tidak valid'],
    ];
@endphp
<div style="display: grid; gap: 1rem; font-size: .875rem;">
    <div style="display: flex; flex-wrap: wrap; gap: .5rem;">
        <x-filament::badge color="success">{{ $counts['valid'] }} baris valid (akan disimpan)</x-filament::badge>
        <x-filament::badge color="gray">{{ $counts['duplicate'] }} duplikat (dilewati)</x-filament::badge>
        <x-filament::badge color="danger">{{ $counts['invalid'] }} tidak valid (dilewati)</x-filament::badge>
    </div>

    @if ($counts['valid'] === 0)
        <p style="font-weight: 600;">Tidak ada baris yang bisa disimpan. Perbaiki file lalu impor ulang.</p>
    @endif

    <div style="max-height: 55vh; overflow: auto; border: 1px solid rgb(148 163 184 / .35); border-radius: .5rem;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="position: sticky; top: 0; background: rgb(148 163 184 / .15); backdrop-filter: blur(6px);">
                <tr style="text-align: left;">
                    <th style="padding: .5rem;">Baris</th>
                    <th style="padding: .5rem;">Status</th>
                    <th style="padding: .5rem;">Tanggal</th>
                    <th style="padding: .5rem;">Judul</th>
                    <th style="padding: .5rem;">Kategori</th>
                    <th style="padding: .5rem;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php [$color, $label] = $badge[$row['status']]; @endphp
                    <tr style="border-top: 1px solid rgb(148 163 184 / .25); vertical-align: top;">
                        <td style="padding: .5rem;">{{ $row['line'] }}</td>
                        <td style="padding: .5rem;"><x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge></td>
                        <td style="padding: .5rem; white-space: nowrap;">
                            {{ $row['start_date'] }}@if ($row['end_date'] !== '' && $row['end_date'] !== $row['start_date']) – {{ $row['end_date'] }}@endif
                        </td>
                        <td style="padding: .5rem;">{{ $row['title'] }}</td>
                        <td style="padding: .5rem;">{{ EventCategory::tryFrom($row['category'])?->label() ?? $row['category'] }}</td>
                        <td style="padding: .5rem;">
                            @if ($row['errors'])
                                {{ implode(' ', $row['errors']) }}
                            @else
                                {{ collect([$row['subcategory'], $row['source']])->filter()->implode(' · ') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
