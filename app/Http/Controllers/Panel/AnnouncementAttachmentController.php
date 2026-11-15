<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh lampiran pengumuman/surat dari disk privat; selain yang berhak → 404 (cegah IDOR). */
class AnnouncementAttachmentController extends Controller
{
    public function __invoke(int $announcement): StreamedResponse
    {
        $record = Announcement::query()->find($announcement);

        abort_if($record === null || Gate::denies('downloadAttachment', $record), 404);

        $disk = Storage::disk(Announcement::DISK);

        abort_unless(filled($record->attachment_path) && $disk->exists($record->attachment_path), 404);

        return $disk->download($record->attachment_path, $record->attachment_name ?: basename($record->attachment_path), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
