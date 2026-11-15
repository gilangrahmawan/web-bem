<?php

use App\Http\Controllers\Public\DivisionController;
use App\Http\Controllers\Public\DownloadController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ProgramController;
use App\Http\Controllers\Public\SeoController;
use App\Http\Controllers\Public\StudyController;
use Illuminate\Support\Facades\Route;

/*
| Website publik (tanpa login). Dashboard pengurus ada di /panel-bem (Filament).
*/

Route::name('public.')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/profil', [PageController::class, 'profile'])->name('profile');
    Route::get('/struktur', [PageController::class, 'structure'])->name('structure');
    Route::get('/kontak', [PageController::class, 'contact'])->name('contact');
    Route::get('/divisi/{division:slug}', DivisionController::class)->name('divisions.show');

    Route::get('/berita', [NewsController::class, 'index'])->name('news.index');
    Route::get('/berita/{slug}', [NewsController::class, 'show'])->name('news.show');
    Route::get('/kajian', [StudyController::class, 'index'])->name('studies.index');
    Route::get('/kajian/{slug}', [StudyController::class, 'show'])->name('studies.show');

    Route::get('/proker', ProgramController::class)->name('programs.index');

    Route::get('/galeri', [GalleryController::class, 'index'])->name('galleries.index');
    Route::get('/galeri/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');

    Route::get('/unduhan', [DownloadController::class, 'index'])->name('downloads.index');
    Route::get('/unduhan/{download}/berkas', [DownloadController::class, 'file'])
        ->whereNumber('download')
        ->middleware('throttle:30,1')
        ->name('downloads.file');

    // Placeholder "segera hadir" — diganti di Fase 3 (kalender), 5 (aspirasi), 7 (pengajuan & oprec).
    $soon = [
        'calendar' => ['/kalender', 'Kalender Kegiatan'],
        'aspirations.create' => ['/aspirasi', 'Kirim Aspirasi'],
        'aspirations.status' => ['/aspirasi/status', 'Cek Status Aspirasi'],
        'submissions.create' => ['/pengajuan', 'Pengajuan Proposal & LPJ'],
        'submissions.status' => ['/pengajuan/status', 'Cek Status Pengajuan'],
        'recruitment.create' => ['/oprec', 'Open Recruitment'],
        'recruitment.results' => ['/oprec/hasil', 'Pengumuman Oprec'],
    ];

    foreach ($soon as $name => [$uri, $title]) {
        Route::get($uri, [PageController::class, 'comingSoon'])->defaults('title', $title)->name($name);
    }
});

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
