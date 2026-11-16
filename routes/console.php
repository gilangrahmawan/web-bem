<?php

use Illuminate\Support\Facades\Schedule;

/*
| Job terjadwal (AGENTS.md B3). Di hosting dijalankan lewat cron:
| * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
*/

// Pengingat tugas konten H-2 & H-1.
Schedule::command('tasks:remind')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Pengingat tunggakan kas & iuran setiap Senin pagi.
Schedule::command('kas:remind-arrears')
    ->weeklyOn(1, '08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Pengingat tenggat LPJ kegiatan (H-3, hari tenggat, mingguan saat terlambat).
Schedule::command('activities:remind-lpj')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Rekap kas bulan lalu ke Bendahara.
Schedule::command('kas:monthly-recap')
    ->monthlyOn(1, '08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Hapus data pribadi pendaftar oprec yang tidak diterima setelah masa simpan (default 6 bulan).
Schedule::command('recruitment:purge-personal-data')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Hapus permanen isi Sampah Drive yang lebih dari 30 hari.
Schedule::command('drive:purge-trash')
    ->dailyAt('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Perawatan chat: sinkron grup otomatis + hapus lampiran chat > 12 bulan.
Schedule::command('chat:maintenance')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Worker antrean (push notification) tanpa Supervisor: diproses tiap menit lalu berhenti (AGENTS.md B2).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
