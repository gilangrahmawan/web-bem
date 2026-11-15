@props([
    'categories',
    // Atribut data yang dibaca JavaScript untuk filter (publik: data-category-toggle, dashboard: data-filter-category).
    'toggleAttribute' => 'data-category-toggle',
])
{{--
    Legenda + filter kalender, sama untuk /kalender dan dashboard.
    Gaya inline agar tampil sama di halaman publik (Tailwind) maupun panel Filament.
    Setiap contoh "Aa" memakai pasangan warna asli kategori (kontras ≥ 4,5:1).
--}}
<div class="bem-legend" style="display: grid; gap: .75rem;">
    <fieldset style="display: flex; flex-wrap: wrap; gap: .5rem; border: 0; padding: 0; margin: 0;">
        <legend style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0);">Tampilkan kategori</legend>
        @foreach ($categories as $category)
            <label style="display: inline-flex; align-items: center; gap: .45rem; padding: .3rem .65rem .3rem .4rem; border-radius: 999px; border: 1px solid rgb(148 163 184 / .45); font-size: .8125rem; font-weight: 600; line-height: 1.2; cursor: pointer;">
                <input type="checkbox" value="{{ $category->value }}" {{ $toggleAttribute }} checked
                    style="width: 1rem; height: 1rem; accent-color: #232361; flex: none;">
                <span aria-hidden="true"
                    style="display: inline-flex; align-items: center; justify-content: center; min-width: 1.75rem; height: 1.35rem; padding: 0 .3rem; border-radius: .3rem; font-size: .75rem; font-weight: 800; background: {{ $category->background() }}; color: {{ $category->text() }};{{ $category->accent() ? ' border-left: 4px solid '.$category->accent().';' : '' }}">Aa</span>
                <span>
                    {{ $category->label() }}
                    @if ($category->legendHint())
                        <span style="display: block; font-size: .6875rem; font-weight: 500; opacity: .75;">{{ $category->legendHint() }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </fieldset>

    <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: .8125rem; align-items: center;">
        <span style="display: inline-flex; align-items: center; gap: .4rem;">
            <span aria-hidden="true" style="display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.35rem; border-radius: .3rem; background: rgb(255 171 171 / .35); color: #C00000; font-weight: 800; font-size: .75rem; border: 1px solid rgb(255 171 171);">7</span>
            Minggu &amp; tanggal Libur Nasional
        </span>
        <span style="display: inline-flex; align-items: center; gap: .4rem;">
            <span aria-hidden="true" style="display: inline-block; width: 1.75rem; height: 1.35rem; border-radius: .3rem; background: rgb(147 247 171 / .35); border: 1px solid rgb(147 247 171);"></span>
            Jumat
        </span>
        <span style="display: inline-flex; align-items: center; gap: .4rem;">
            <span aria-hidden="true">⚠</span> Tanggal bentrok kegiatan BEM/UKM
        </span>
    </div>
</div>
