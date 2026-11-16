<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran chat dari disk privat — HANYA anggota percakapan (juga berlaku bagi Super Admin).
 * Selain itu, pesan dihapus, atau lampiran kedaluwarsa → 404.
 */
class ChatAttachmentController extends Controller
{
    public function __invoke(Request $request, int $message): StreamedResponse
    {
        $record = Message::query()->with('conversation')->find($message);

        abort_if($record === null || ! $record->hasAttachment() || Gate::forUser($request->user())->denies('view', $record->conversation), 404);

        $disk = Storage::disk(Message::DISK);
        abort_unless($disk->exists($record->attachment_path), 404);

        $name = (string) $record->attachment_name;
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($name)) ?: 'lampiran';

        return $disk->response($record->attachment_path, $name, [
            'Content-Type' => $record->attachment_mime,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $record->isImage() ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback,
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
