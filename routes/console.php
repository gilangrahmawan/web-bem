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
