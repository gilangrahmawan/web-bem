<?php

namespace App\Services\Letters;

use App\Models\Announcement;

/**
 * Arsip otomatis surat Humas ke folder Drive "Surat" (AGENTS.md C3 Humas).
 *
 * TODO(Fase 8 — Drive): buat implementasi nyata (mis. DriveLetterArchiver) yang menyalin surat +
 * lampirannya ke folder sistem "Surat", lalu ganti binding di AppServiceProvider::register().
 * Sampai saat itu dipakai NullLetterArchiver (tidak melakukan apa-apa).
 */
interface LetterArchiver
{
    public function archive(Announcement $letter): void;
}
