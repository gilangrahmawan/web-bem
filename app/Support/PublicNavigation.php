<?php

namespace App\Support;

/**
 * Struktur navigasi website publik (AGENTS.md Fase 2 langkah 1).
 */
class PublicNavigation
{
    /**
     * @return list<array{label: string, route?: string, active?: string, children?: list<array{label: string, route: string, description: string}>}>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Beranda', 'route' => 'public.home', 'active' => 'public.home'],
            ['label' => 'Profil', 'route' => 'public.profile', 'active' => 'public.profile'],
            ['label' => 'Struktur', 'route' => 'public.structure', 'active' => 'public.structure|public.divisions.*'],
            ['label' => 'Berita', 'route' => 'public.news.index', 'active' => 'public.news.*'],
            ['label' => 'Kajian', 'route' => 'public.studies.index', 'active' => 'public.studies.*'],
            ['label' => 'Kalender', 'route' => 'public.calendar', 'active' => 'public.calendar'],
            ['label' => 'Unduhan', 'route' => 'public.downloads.index', 'active' => 'public.downloads.*'],
            [
                'label' => 'Layanan',
                'active' => 'public.aspirations.*|public.submissions.*|public.recruitment.*',
                'children' => [
                    ['label' => 'Aspirasi', 'route' => 'public.aspirations.create', 'description' => 'Sampaikan aspirasi, boleh anonim'],
                    ['label' => 'Pengajuan Proposal/LPJ', 'route' => 'public.submissions.create', 'description' => 'Ajukan proposal atau LPJ kegiatan'],
                    ['label' => 'Oprec', 'route' => 'public.recruitment.create', 'description' => 'Pendaftaran pengurus BEM'],
                ],
            ],
            ['label' => 'Kontak', 'route' => 'public.contact', 'active' => 'public.contact'],
        ];
    }

    public static function isActive(?string $pattern): bool
    {
        return $pattern !== null && request()->routeIs(...explode('|', $pattern));
    }
}
