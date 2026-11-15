@php
    use App\Support\Format;
@endphp
<x-layouts.public title="Struktur Kepengurusan" description="Struktur kepengurusan BEM UBHINUS periode aktif: BPH dan divisi.">
    <x-public.page-header eyebrow="Struktur Kepengurusan" :title="$period?->name ?? 'Struktur Kepengurusan'"
        :subtitle="$period ? 'Periode '.Format::date($period->starts_on).' – '.Format::date($period->ends_on) : null" />

    <div class="mx-auto max-w-7xl space-y-16 px-4 py-16 sm:px-6 lg:px-8">
        @if (! $period || ($divisions->isEmpty() && $unassigned->isEmpty()))
            <x-public.empty-state icon="users" title="Struktur kepengurusan belum tersedia"
                message="Data struktur untuk periode aktif belum diisi." />
        @endif

        @if ($unassigned->isNotEmpty())
            <section>
                <h2 class="text-center text-2xl font-extrabold text-brand-primary">Pimpinan</h2>
                <div class="mx-auto mt-8 grid max-w-3xl gap-6 sm:grid-cols-2">
                    @foreach ($unassigned as $member)
                        @include('public.partials.member-card', ['member' => $member, 'highlight' => true])
                    @endforeach
                </div>
            </section>
        @endif

        @foreach ($divisions as $division)
            <section>
                <div class="flex flex-col items-center text-center">
                    <h2 class="text-2xl font-extrabold text-brand-primary">{{ $division->slug === 'bph' ? 'Badan Pengurus Harian' : $division->name }}</h2>
                    @if ($division->slug !== 'bph')
                        <a href="{{ route('public.divisions.show', $division->slug) }}" class="mt-1 text-sm font-semibold text-brand-secondary hover:underline">
                            Lihat divisi & program kerja →
                        </a>
                    @endif
                </div>
                <div @class([
                    'mt-8 grid gap-6',
                    'sm:grid-cols-2 lg:grid-cols-4' => true,
                ])>
                    @foreach ($division->structureMembers as $member)
                        @include('public.partials.member-card', ['member' => $member, 'highlight' => $division->slug === 'bph' && $loop->index < 2])
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.public>
