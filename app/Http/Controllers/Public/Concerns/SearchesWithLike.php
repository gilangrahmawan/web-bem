<?php

namespace App\Http\Controllers\Public\Concerns;

trait SearchesWithLike
{
    /**
     * Membersihkan kata kunci pencarian: dipangkas, dibatasi panjangnya,
     * dan karakter wildcard LIKE (% _ \) di-escape. Nilai tetap dikirim sebagai binding.
     */
    protected function likeTerm(?string $term): ?string
    {
        $term = trim(mb_substr((string) $term, 0, 100));

        if ($term === '') {
            return null;
        }

        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
