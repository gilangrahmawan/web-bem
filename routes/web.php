<?php

use App\Http\Controllers\Panel\AnnouncementAttachmentController;
use App\Http\Controllers\Panel\TaskResultController;
use App\Http\Controllers\Public\AspirationController;
use App\Http\Controllers\Public\CalendarController;
use App\Http\Controllers\Public\DivisionController;
use App\Http\Controllers\Public\DownloadController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ProgramController;
use App\Http\Controllers\Public\SeoController;
use App\Http\Controllers\Public\StudyController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsurePasswordChanged;
use Filament\Http\Middleware\SetUpPanel;
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

    Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/kalender/agenda', [CalendarController::class, 'feed'])
        ->middleware('throttle:60,1')
        ->name('calendar.feed');

    // Aspirasi: kirim (5/IP/jam) & cek status (20/IP/jam), keduanya dengan Turnstile.
    Route::get('/aspirasi', [AspirationController::class, 'create'])->name('aspirations.create');
    Route::post('/aspirasi', [AspirationController::class, 'store'])
        ->middleware('throttle:public-submit')
        ->name('aspirations.store');
    Route::get('/aspirasi/status', [AspirationController::class, 'status'])->name('aspirations.status');
    Route::post('/aspirasi/status', [AspirationController::class, 'check'])
        ->middleware('throttle:public-status')
        ->name('aspirations.check');

    // Placeholder "segera hadir" — diganti di Fase 7 (pengajuan & oprec).
    $soon = [
        'submissions.create' => ['/pengajuan', 'Pengajuan Proposal & LPJ'],
        'submissions.status' => ['/pengajuan/status', 'Cek Status Pengajuan'],
        'recruitment.create' => ['/oprec', 'Open Recruitment'],
        'recruitment.results' => ['/oprec/hasil', 'Pengumuman Oprec'],
    ];

    foreach ($soon as $name => [$uri, $title]) {
        Route::get($uri, [PageController::class, 'comingSoon'])->defaults('title', $title)->name($name);
    }
});

/*
| Unduhan berkas privat dashboard (di luar Livewire). Login wajib; otorisasi lewat policy.
*/
Route::middleware([SetUpPanel::class.':admin', Authenticate::class, EnsurePasswordChanged::class])
    ->prefix('panel-bem')->name('panel.')->group(function (): void {
        Route::get('/tugas/{assignee}/hasil', TaskResultController::class)
            ->whereNumber('assignee')
            ->name('tasks.result');
        Route::get('/pesan/{announcement}/lampiran', AnnouncementAttachmentController::class)
            ->whereNumber('announcement')
            ->name('announcements.attachment');
    });

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
