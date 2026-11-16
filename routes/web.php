<?php

use App\Http\Controllers\Panel\ActivityFileController;
use App\Http\Controllers\Panel\AnnouncementAttachmentController;
use App\Http\Controllers\Panel\Drive\DocumentExportController;
use App\Http\Controllers\Panel\Drive\DriveFileController;
use App\Http\Controllers\Panel\Drive\DriveFolderZipController;
use App\Http\Controllers\Panel\PostImageController;
use App\Http\Controllers\Panel\RecruitmentFileController;
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
use App\Http\Controllers\Public\RecruitmentController;
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

    // Pengajuan kegiatan: halaman informasi; pengajuan dilakukan setelah login (Fase 7).
    Route::get('/pengajuan', [PageController::class, 'submissions'])->name('submissions.create');
    Route::permanentRedirect('/pengajuan/status', '/pengajuan')->name('submissions.status');

    // Oprec: daftar (5/IP/jam) & cek hasil (20/IP/jam), keduanya dengan Turnstile.
    Route::get('/oprec', [RecruitmentController::class, 'create'])->name('recruitment.create');
    Route::post('/oprec', [RecruitmentController::class, 'store'])
        ->middleware('throttle:public-submit')
        ->name('recruitment.store');
    Route::get('/oprec/hasil', [RecruitmentController::class, 'results'])->name('recruitment.results');
    Route::post('/oprec/hasil', [RecruitmentController::class, 'check'])
        ->middleware('throttle:public-status')
        ->name('recruitment.check');
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
        Route::get('/konten/{post}/gambar', PostImageController::class)
            ->whereNumber('post')
            ->name('posts.image');
        Route::get('/kegiatan/{activity}/berkas/{type}', ActivityFileController::class)
            ->whereNumber('activity')
            ->whereIn('type', ['proposal', 'lpj'])
            ->name('activities.file');
        Route::get('/oprec/pendaftar/{applicant}/{type}', RecruitmentFileController::class)
            ->whereNumber('applicant')
            ->whereIn('type', ['cv', 'photo'])
            ->name('recruitment.file');

        // Drive (Fase 8): semua lewat policy; tidak berhak → 404.
        Route::get('/drive/berkas/{file}/unduh', [DriveFileController::class, 'download'])
            ->whereNumber('file')->middleware('throttle:120,1')->name('drive.files.download');
        Route::get('/drive/berkas/{file}/pratinjau', [DriveFileController::class, 'preview'])
            ->whereNumber('file')->middleware('throttle:120,1')->name('drive.files.preview');
        Route::get('/drive/berkas/{file}/thumbnail', [DriveFileController::class, 'thumbnail'])
            ->whereNumber('file')->name('drive.files.thumbnail');
        Route::get('/drive/folder/{folder}/zip', DriveFolderZipController::class)
            ->whereNumber('folder')->middleware('throttle:10,1')->name('drive.folders.zip');
        Route::get('/drive/dokumen/{document}/export/{format}', DocumentExportController::class)
            ->whereNumber('document')->whereIn('format', ['pdf', 'docx'])->middleware('throttle:30,1')->name('drive.documents.export');
    });

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
