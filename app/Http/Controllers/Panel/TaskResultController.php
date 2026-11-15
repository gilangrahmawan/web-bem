<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EventAssignee;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh berkas hasil tugas konten dari disk privat (AGENTS.md B4).
 * Selalu diunduh sebagai attachment (tidak ditampilkan inline) dan ZIP tidak pernah diekstrak.
 */
class TaskResultController extends Controller
{
    public function __invoke(int $assignee): StreamedResponse
    {
        $record = EventAssignee::query()->with('event')->find($assignee);

        // 404 (bukan 403) agar ID milik orang lain tidak bisa ditebak keberadaannya.
        abort_if($record === null || Gate::denies('view', $record), 404);

        $disk = Storage::disk(EventAssignee::DISK);

        abort_unless(filled($record->result_path) && $disk->exists($record->result_path), 404);

        return $disk->download($record->result_path, $record->result_original_name ?: basename($record->result_path), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
