<?php

declare(strict_types=1);

use App\Filament\Pages\Calendar;
use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\Inbox;
use App\Filament\Pages\KasWeekly;
use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\MyProfile;
use App\Filament\Pages\MyTasks;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Aspirations\AspirationResource;
use App\Filament\Resources\Discussions\DiscussionResource;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Filament\Resources\Downloads\DownloadResource;
use App\Filament\Resources\Dues\DueResource;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Resources\Letters\LetterResource;
use App\Filament\Resources\Periods\PeriodResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\StructureMembers\StructureMemberResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AspirationStatusChart;
use App\Filament\Widgets\AspirationWeeklyChart;
use App\Filament\Widgets\ContentQueueWidget;
use App\Filament\Widgets\DiscussionStatusChart;
use App\Filament\Widgets\DuesProgressChart;
use App\Filament\Widgets\InboxStatsWidget;
use App\Filament\Widgets\KasArrearsByDivisionChart;
use App\Filament\Widgets\KasStatsOverview;
use App\Filament\Widgets\KasStatusChart;
use App\Filament\Widgets\KasWeeklyChart;
use App\Filament\Widgets\MessageReadRateChart;
use App\Filament\Widgets\MyKasWidget;
use App\Filament\Widgets\MyPostsChart;
use App\Filament\Widgets\PostsPublishedChart;
use App\Filament\Widgets\PostStatusChart;
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
            DownloadResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
            GalleryResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
            ProgramResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
            StructureMemberResource::class => ['viewAny', 'view', 'create', 'update', 'delete'],
        ],
        // Dijaga permission kustom (due.manage / kas.view_all) lewat DuePolicy, tanpa permission Shield baru.
        'exclude' => [
            DueResource::class,
            // Fase 5: dijaga policy + permission kustom (aspiration.*, discussion.*, announcement.create, letter.create).
            AspirationResource::class,
            DiscussionResource::class,
            AnnouncementResource::class,
            LetterResource::class,
            // Fase 6: dijaga PostPolicy + permission kustom post.*.
            PostResource::class,
        ],
    ],

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
            ChangePassword::class,  // semua pengurus wajib bisa mengakses
            MyProfile::class,       // semua pengurus (termasuk pembina) mengelola profilnya sendiri
            ManageSettings::class,  // dijaga permission kustom setting.manage
            Calendar::class,        // semua pengurus; isi dibatasi EventPolicy + permission event.*
            MyTasks::class,         // semua pengurus kecuali pembina; dibatasi EventAssigneePolicy
            KasWeekly::class,       // kas.view_all / kas.manage
            Inbox::class,           // semua pengurus (hanya pesan miliknya)
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
            // Widget kas: hak akses diatur canView() (kas.view_all / pengurus wajib kas).
            KasStatsOverview::class,
            KasWeeklyChart::class,
            KasStatusChart::class,
            KasArrearsByDivisionChart::class,
            DuesProgressChart::class,
            MyKasWidget::class,
            // Widget Fase 5: hak akses diatur canView().
            AspirationStatusChart::class,
            AspirationWeeklyChart::class,
            InboxStatsWidget::class,
            MessageReadRateChart::class,
            DiscussionStatusChart::class,
            // Widget Fase 6.
            PostStatusChart::class,
            PostsPublishedChart::class,
            ContentQueueWidget::class,
            MyPostsChart::class,
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
        'post.create_study' => 'Buat kajian publik',
        'post.approve_coordinator' => 'Setujui konten (koordinator)',
        'post.approve_leader' => 'Setujui konten (ketua/wakil)',
        'post.publish' => 'Terbitkan konten',
        'aspiration.view' => 'Lihat aspirasi',
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
