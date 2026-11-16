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

    // HSTS (Fase 10): JANGAN dinyalakan sebelum HTTPS terbukti normal beberapa hari (lihat DEPLOY.md).
    // Hanya dikirim pada request HTTPS.
    'hsts' => [
        'enabled' => (bool) env('HSTS_ENABLED', false),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
    ],

];
