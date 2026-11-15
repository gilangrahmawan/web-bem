{{-- Satu program kerja. Variabel: $program --}}
@php
    use App\Enums\ProgramStatus;
    use App\Support\Format;

    $badge = match ($program->status) {
        ProgramStatus::Done => 'bg-emerald-100 text-emerald-800',
        ProgramStatus::Ongoing => 'bg-amber-100 text-amber-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<li class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h3 class="font-bold text-slate-900">{{ $program->title }}</h3>
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge }}">{{ $program->status->label() }}</span>
    </div>
    @if ($program->description)
        <p class="mt-2 text-sm text-slate-600">{{ $program->description }}</p>
    @endif
    @if ($program->starts_on)
        <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
            <x-public.icon name="calendar" class="size-4" />
            {{ Format::date($program->starts_on) }}@if ($program->ends_on && ! $program->ends_on->equalTo($program->starts_on)) – {{ Format::date($program->ends_on) }}@endif
        </p>
    @endif
</li>
