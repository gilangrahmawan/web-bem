<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentApplicant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CV (unduh) & foto (tampil inline) pendaftar oprec dari disk privat.
 * Selain yang berhak melihat pendaftar → 404 (cegah IDOR).
 */
class RecruitmentFileController extends Controller
{
    public function __invoke(int $applicant, string $type): StreamedResponse
    {
        $record = RecruitmentApplicant::query()->find($applicant);

        abort_if($record === null || Gate::denies('view', $record), 404);

        $path = $type === 'cv' ? $record->cv_path : $record->photo_path;
        $disk = Storage::disk(RecruitmentApplicant::DISK);
        abort_unless(filled($path) && $disk->exists($path), 404);

        if ($type === 'photo') {
            return $disk->response($path, 'foto-'.$record->registration_code.'.webp', [
                'Content-Type' => 'image/webp',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        return $disk->download($path, 'CV-'.Str::slug($record->name).'-'.$record->registration_code.'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
