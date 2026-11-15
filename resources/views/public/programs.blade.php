@php
    use App\Enums\ProgramStatus;
@endphp
<x-layouts.public title="Program Kerja" description="Program kerja BEM UBHINUS per divisi: rencana, berjalan, dan selesai.">
    <x-public.page-header eyebrow="Program Kerja" title="Program Kerja"
        :subtitle="$period ? 'Program kerja '.$period->name.' per divisi.' : 'Program kerja BEM UBHINUS per divisi.'" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Filter status">
            @include('public.partials.filter-pill', ['href' => route('public.programs.index'), 'label' => 'Semua', 'active' => $activeStatus === null])
            @foreach (ProgramStatus::cases() as $status)
                @include('public.partials.filter-pill', [
                    'href' => route('public.programs.index', ['status' => $status->value]),
                    'label' => $status->label(),
                    'active' => $activeStatus === $status,
                ])
            @endforeach
        </nav>

        @forelse ($divisions as $division)
            <section class="mt-12">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-extrabold text-brand-primary">{{ $division->name }}</h2>
                    <a href="{{ route('public.divisions.show', $division->slug) }}" class="text-sm font-semibold text-brand-secondary hover:underline">Profil divisi →</a>
                </div>
                <ul class="mt-6 grid gap-4 md:grid-cols-2">
                    @foreach ($division->programs as $program)
                        @include('public.partials.program-item', ['program' => $program])
                    @endforeach
                </ul>
            </section>
        @empty
            <x-public.empty-state class="mt-10" title="Belum ada program kerja" message="Program kerja untuk periode aktif belum diisi." />
        @endforelse
    </div>
</x-layouts.public>
