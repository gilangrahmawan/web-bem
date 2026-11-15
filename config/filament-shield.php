<?php

declare(strict_types=1);

use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Filament\Resources\Periods\PeriodResource;
use App\Filament\Resources\Users\UserResource;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

/*
| Konfigurasi Filament Shield untuk BEM UBHINUS.
|
| Nama permission resource dibentuk oleh closure di AppServiceProvider
| (FilamentShield::buildPermissionKeyUsing) menjadi format `view_any_user`,
| sesuai AGENTS.md. Shield melarang separator "_" bersama case "snake",
| sehingga di sini tetap ":" + "snake" dan closure-lah yang menentukan hasil akhirnya.
|
| Permission kustom (`modul.aksi`) dipakai apa adanya (tidak diformat ulang).
| Daftar lengkapnya juga dipakai oleh RolePermissionSeeder.
*/

return [

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => false,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    'tenant_model' => null,

    'auth_provider_model' => 'App\\Models\\User',

    // Gate::before untuk super_admin didefinisikan sendiri di AppServiceProvider
    // (dengan pengecualian aturan yang tetap berlaku bagi super_admin).
    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    // Setiap pengurus wajib punya role BEM; role generik panel_user tidak dipakai.
    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    'permissions' => [
        'separator' => ':',
        'case' => 'snake',
        'generate' => true,
        'format_custom_permission_keys' => false,
    ],

    // Policy ditulis manual di app/Policies (berisi aturan kepemilikan), jangan di-generate.
    'policies' => [
        'path' => app_path('Policies'),
        'merge' => false,
        'generate' => false,
        'methods' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'single_parameter_methods' => ['viewAny', 'create', 'deleteAny'],
    ],

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    'resources' => [
        'subject' => 'model',
        'manage' => [
            RoleResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
            UserResource::class => ['viewAny', 'view', 'create', 'update'],
            DivisionResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
            PeriodResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
            ActivityLogResource::class => ['viewAny', 'view'],
        ],
        'exclude' => [
            //
        ],
    ],

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
            ChangePassword::class,  // semua pengurus wajib bisa mengakses
            ManageSettings::class,  // dijaga permission kustom setting.manage
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    // BAGIAN D2 AGENTS.md. Kunci = nama permission, nilai = label (Indonesia).
    'custom_permissions' => [
        'user.manage' => 'Kelola user',
        'role.manage' => 'Kelola role',
        'setting.manage' => 'Kelola pengaturan',
        'event.create_ukm' => 'Tambah agenda UKM',
        'event.create_public' => 'Tambah agenda publik',
        'event.create_meeting' => 'Tambah rapat',
        'event.assign_task' => 'Tugaskan konten',
        'event.manage_all' => 'Kelola semua agenda',
        'kas.manage' => 'Kelola kas',
        'kas.view_all' => 'Lihat semua kas',
        'due.manage' => 'Kelola iuran',
        'discussion.create' => 'Tambah pembahasan',
        'discussion.set_docs_link' => 'Isi link Docs pembahasan',
        'discussion.manage_all' => 'Kelola semua pembahasan',
        'post.create' => 'Buat konten',
        'post.create_press_release' => 'Buat press release',
        'post.approve_coordinator' => 'Setujui konten (koordinator)',
        'post.approve_leader' => 'Setujui konten (ketua/wakil)',
        'post.publish' => 'Terbitkan konten',
        'aspiration.handle' => 'Tangani aspirasi',
        'aspiration.escalate' => 'Eskalasi aspirasi',
        'aspiration.forward_to_kajian' => 'Teruskan aspirasi ke Kajian',
        'aspiration.study' => 'Kaji aspirasi',
        'announcement.create' => 'Buat pengumuman',
        'letter.create' => 'Buat surat',
        'folder.manage' => 'Kelola folder',
        'download.manage' => 'Kelola unduhan',
        'submission.verify' => 'Verifikasi pengajuan',
        'submission.review_budget' => 'Review anggaran pengajuan',
        'submission.decide' => 'Putuskan pengajuan',
        'recruitment.manage' => 'Kelola oprec',
        'chat.use' => 'Gunakan chat',
        'report.view' => 'Lihat laporan',
    ],

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    'register_role_policy' => true,

];
