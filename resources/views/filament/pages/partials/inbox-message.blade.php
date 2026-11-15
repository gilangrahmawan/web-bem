@php
    use App\Support\Format;

    $announcement = $recipient->announcement;
@endphp
<div style="display: grid; gap: 1rem; font-size: .875rem;">
    <div style="opacity: .75;">
        {{ $announcement->type->label() }} dari {{ $announcement->creator?->name ?? 'BEM' }}
        · {{ Format::date($announcement->created_at) }}, {{ $announcement->created_at->format('H.i') }}
    </div>
    <div style="white-space: pre-line; line-height: 1.6;">{{ $announcement->body }}</div>
    @if ($announcement->attachment_path)
        <a href="{{ route('panel.announcements.attachment', $announcement) }}" style="text-decoration: underline; font-weight: 600;">
            Unduh lampiran: {{ $announcement->attachment_name }}
        </a>
    @endif
    @if ($recipient->reply)
        <div style="padding: .75rem; border-radius: .5rem; background: rgb(148 163 184 / .12);">
            <strong>Balasan Anda:</strong>
            <div style="white-space: pre-line;">{{ $recipient->reply }}</div>
        </div>
    @endif
</div>
