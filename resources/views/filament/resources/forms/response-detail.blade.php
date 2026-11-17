{{-- Detail satu jawaban (modal). Berkas diunduh lewat controller berotorisasi. --}}
<div style="display: grid; gap: .9rem; font-size: .9rem;">
    <div style="color: rgb(100 116 139);">
        Dikirim {{ $response->submitted_at->timezone(config('app.timezone'))->translatedFormat('j F Y, H:i') }} WIB
        @if ($response->respondent) · {{ $response->respondent->name }} @endif
        @if ($response->respondent_email) · {{ $response->respondent_email }} @endif
        @if ($isQuiz)
            · Nilai <strong>{{ rtrim(rtrim(number_format((float) $response->score, 2, ',', '.'), '0'), ',') }} / {{ rtrim(rtrim(number_format((float) $response->max_score, 2, ',', '.'), '0'), ',') }}</strong>
            @if ($response->tab_switches > 0) · Pindah tab {{ $response->tab_switches }}× @endif
        @endif
    </div>

    @foreach ($columns as $question)
        @php $answer = $answers->get($question->getKey()); @endphp
        <div style="border: 1px solid rgb(148 163 184 / .4); border-radius: .75rem; padding: .8rem 1rem;">
            <div style="font-weight: 600;">{{ $question->label }} @if ($question->trashed())<span style="color: rgb(185 28 28); font-weight: 400;">(dihapus)</span>@endif</div>

            @if ($question->type === \App\Enums\QuestionType::FileUpload)
                @forelse ($answer?->files ?? [] as $index => $file)
                    <div><a href="{{ route('panel.forms.answer-file', [$answer->getKey(), $index]) }}" style="color: #232361; text-decoration: underline;">{{ $file['name'] }}</a> <span style="color: rgb(100 116 139);">({{ \App\Support\Format::bytes($file['size'] ?? 0) }})</span></div>
                @empty
                    <div style="color: rgb(100 116 139);">—</div>
                @endforelse
            @else
                <div style="white-space: pre-line;">{{ \App\Services\Forms\FormExport::display($question, $answer) ?: '—' }}</div>
            @endif

            @if ($isQuiz && $answer && $question->points > 0)
                <div style="margin-top: .4rem; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap;">
                    <span>Poin: <strong>{{ $answer->points_awarded === null ? 'belum dinilai' : rtrim(rtrim(number_format($answer->points_awarded, 2, ',', '.'), '0'), ',') }}</strong> / {{ $question->points }}</span>
                    @if ($canGrade && $question->type->isManuallyGraded())
                        <x-filament::button size="xs" wire:click="mountAction('gradeAnswer', { answer: {{ $answer->getKey() }} })">Beri nilai</x-filament::button>
                    @endif
                </div>
                @if ($answer->grader_note && $canGrade)
                    <div style="color: rgb(100 116 139); font-size: .8rem;">Catatan penilai: {{ $answer->grader_note }}</div>
                @endif
            @endif
        </div>
    @endforeach
</div>
