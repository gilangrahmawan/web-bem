<?php

/*
| Konfigurasi khusus aplikasi BEM UBHINUS.
| Nilai rahasia hanya dibaca dari .env (tidak pernah ditulis di sini).
*/

return [

    // Akun Super Admin pertama (Ketua). Dipakai oleh SuperAdminSeeder.
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    // Password akun uji DemoSeeder. HANYA UNTUK LOKAL.
    'demo_password' => env('DEMO_PASSWORD'),

    // Penanda nilai placeholder di .env yang belum diisi pemilik.
    'placeholder_marker' => 'GANTI',

    'login' => [
        'max_attempts' => 5,
        'lockout_seconds' => 15 * 60,
    ],

];
