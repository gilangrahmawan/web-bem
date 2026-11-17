<?php

namespace App\Http\Controllers\Panel\Drive;

use App\Enums\DriveFileKind;
use App\Http\Controllers\Controller;
use App\Models\DriveFile;
use App\Models\User;
use App\Services\Drive\DriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berkas Drive dari disk privat. Tidak berhak / di Sampah / tidak ada → 404 (cegah IDOR & menebak ID).
 * - download: lampiran (nama asli).
 * - preview: inline hanya untuk gambar & PDF (viewer bawaan browser, iframe same-origin) + CSP sandbox.
 * - thumbnail: WebP kecil untuk kartu (bila ada).
 */
class DriveFileController extends Controller
{
    public function download(Request $request, int $file, DriveService $drive): StreamedResponse
    {
        $record = $this->resolve($request, $file);
        $drive->recordOpen($this->user($request), $record);

        return Storage::disk($record->disk)->download($record->path(), $record->original_name, [
            'Content-Type' => $record->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, int $file, DriveService $drive): StreamedResponse
    {
        $record = $this->resolve($request, $file);
        abort_unless($record->kind->previewable(), 404);
        $drive->recordOpen($this->user($request), $record);

        return Storage::disk($record->disk)->response($record->path(), $record->original_name, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $record->original_name,
                preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($record->original_name)) ?: 'berkas',
            ),
            'X-Content-Type-Options' => 'nosniff',
            // Gambar: sandbox penuh. PDF: tanpa "sandbox" karena viewer PDF Chrome/Edge menolak berjalan di
            // dokumen ber-sandbox; isolasi tetap lewat default-src 'none' + MIME terverifikasi + nosniff.
            'Content-Security-Policy' => ($record->kind === DriveFileKind::Pdf ? '' : 'sandbox; ')
                ."default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; object-src 'self'; frame-ancestors 'self'",
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function thumbnail(Request $request, int $file): StreamedResponse
    {
        $record = $this->resolve($request, $file);
        abort_if(blank($record->thumbnail_path) || ! Storage::disk($record->disk)->exists($record->thumbnail_path), 404);

        return Storage::disk($record->disk)->response($record->thumbnail_path, 'thumb.webp', [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function resolve(Request $request, int $id): DriveFile
    {
        $record = DriveFile::query()->find($id);

        abort_if($record === null || Gate::forUser($this->user($request))->denies('view', $record), 404);
        abort_unless(Storage::disk($record->disk)->exists($record->path()), 404);

        return $record;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
