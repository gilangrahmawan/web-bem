<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\StudentActivity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh proposal/LPJ dari disk privat. Selain yang berhak melihat kegiatan → 404 (cegah IDOR). */
class ActivityFileController extends Controller
{
    public function __invoke(int $activity, string $type): StreamedResponse
    {
        $record = StudentActivity::query()->find($activity);

        abort_if($record === null || Gate::denies('view', $record), 404);

        [$path, $name] = $type === 'lpj'
            ? [$record->lpj_path, $record->lpj_name]
            : [$record->proposal_path, $record->proposal_name];

        $disk = Storage::disk(StudentActivity::DISK);
        abort_unless(filled($path) && $disk->exists($path), 404);

        return $disk->download($path, $name ?: basename($path), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
