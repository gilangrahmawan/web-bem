<?php

namespace App\Services\Letters;

use App\Models\Announcement;

/**
 * Arsip otomatis surat Humas ke folder Drive "Surat" (AGENTS.md C3 Humas).
 *
 * Implementasi: DriveLetterArchiver (Fase 8) — lampiran disalin + dokumen ringkasan surat ke
 * folder sistem "Surat/{tahun}". Binding di AppServiceProvider::register().
 */
interface LetterArchiver
{
    public function archive(Announcement $letter): void;
}
