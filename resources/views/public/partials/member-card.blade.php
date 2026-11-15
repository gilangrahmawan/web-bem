{{-- Kartu anggota struktur. Variabel: $member (StructureMember), $highlight (bool) --}}
<div @class([
    'flex flex-col items-center rounded-2xl p-5 text-center',
    'bg-brand-primary text-white shadow-lg' => $highlight ?? false,
    'bg-white ring-1 ring-slate-200' => ! ($highlight ?? false),
])>
    <img src="{{ $member->photoUrl() }}" alt="Foto {{ $member->name }}" loading="lazy" decoding="async" width="112" height="112"
        @class(['size-28 rounded-full object-cover ring-4', 'ring-brand-accent' => $highlight ?? false, 'ring-brand-primary-100' => ! ($highlight ?? false)])>
    <p @class(['mt-4 font-bold', 'text-white' => $highlight ?? false, 'text-slate-900' => ! ($highlight ?? false)])>{{ $member->name }}</p>
    <p @class(['text-sm font-semibold', 'text-brand-accent' => $highlight ?? false, 'text-brand-secondary' => ! ($highlight ?? false)])>{{ $member->position }}</p>
    @if ($member->prodi)
        <p @class(['mt-1 text-xs', 'text-white/70' => $highlight ?? false, 'text-slate-500' => ! ($highlight ?? false)])>{{ $member->prodi }}</p>
    @endif
</div>
