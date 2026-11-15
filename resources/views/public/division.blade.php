<x-layouts.public :title="$division->name" :description="$division->description">
    <x-public.page-header eyebrow="Divisi" :title="$division->name" :subtitle="$division->description" />

    <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[2fr_1fr] lg:px-8">
        <section>
            <h2 class="text-2xl font-extrabold text-brand-primary">Program Kerja</h2>
            @if ($programs->isNotEmpty())
                <ul class="mt-6 space-y-4">
                    @foreach ($programs as $program)
                        @include('public.partials.program-item', ['program' => $program])
                    @endforeach
                </ul>
            @else
                <x-public.empty-state class="mt-6" title="Belum ada program kerja" message="Program kerja divisi untuk periode aktif belum diisi." />
            @endif
        </section>

        <aside>
            <h2 class="text-2xl font-extrabold text-brand-primary">Anggota</h2>
            @if ($members->isNotEmpty())
                <ul class="mt-6 space-y-3">
                    @foreach ($members as $member)
                        <li class="flex items-center gap-4 rounded-2xl bg-white p-3 ring-1 ring-slate-200">
                            <img src="{{ $member->photoUrl() }}" alt="" width="56" height="56" loading="lazy" class="size-14 rounded-full object-cover">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900">{{ $member->name }}</p>
                                <p class="text-sm text-brand-secondary">{{ $member->position }}</p>
                                @if ($member->prodi)
                                    <p class="text-xs text-slate-500">{{ $member->prodi }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-public.empty-state class="mt-6" icon="users" title="Data anggota belum tersedia" />
            @endif
        </aside>
    </div>
</x-layouts.public>
