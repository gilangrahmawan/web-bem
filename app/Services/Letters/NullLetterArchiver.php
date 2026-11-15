<?php

namespace App\Services\Letters;

use App\Models\Announcement;

/** Placeholder sampai Drive tersedia (Fase 8). Lihat TODO di LetterArchiver. */
class NullLetterArchiver implements LetterArchiver
{
    public function archive(Announcement $letter): void
    {
        // Sengaja kosong.
    }
}
