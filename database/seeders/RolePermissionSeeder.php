<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 10 role bawaan + permission (AGENTS.md BAGIAN D). Aman dijalankan berulang.
 *
 * Catatan: super_admin juga lolos lewat Gate::before; permission tetap diberikan
 * agar tampil benar di halaman Role & Permission.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permission resource Filament (format Shield `aksi_model`).
     * Harus sama dengan hasil discovery Shield (diuji di ShieldPermissionNamesTest).
     */
    public const RESOURCE_PERMISSIONS = [
        'view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role',
        'view_any_user', 'view_user', 'create_user', 'update_user',
        'view_any_division', 'view_division', 'create_division', 'update_division', 'delete_division',
        'view_any_period', 'view_period', 'create_period', 'update_period', 'delete_period',
        'view_any_activity_log', 'view_activity_log',
        'view_any_download', 'view_download', 'create_download', 'update_download', 'delete_download',
        'view_any_gallery', 'view_gallery', 'create_gallery', 'update_gallery', 'delete_gallery',
        'view_any_program', 'view_program', 'create_program', 'update_program', 'delete_program',
        'view_any_structure_member', 'view_structure_member', 'create_structure_member',
        'update_structure_member', 'delete_structure_member',
    ];

    private const MANAGE_DOWNLOADS = ['view_any_download', 'view_download', 'create_download', 'update_download', 'delete_download'];

    private const MANAGE_GALLERIES = ['view_any_gallery', 'view_gallery', 'create_gallery', 'update_gallery', 'delete_gallery'];

    /** Koordinator: dibatasi ke divisinya sendiri oleh ProgramPolicy. */
    private const MANAGE_PROGRAMS = ['view_any_program', 'view_program', 'create_program', 'update_program', 'delete_program'];

    /**
     * Pemetaan role → permission (selain super_admin yang mendapat semuanya).
     * Permission modul fase berikutnya bersifat awal dan dapat disesuaikan di fasenya.
     *
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            RoleName::Sekretaris->value => [
                'announcement.create', 'folder.manage', 'download.manage',
                'submission.verify', 'recruitment.manage',
                'event.create_public', 'event.create_ukm', 'event.create_meeting',
                'chat.use',
                ...self::MANAGE_DOWNLOADS,
            ],
            RoleName::Bendahara->value => [
                'kas.manage', 'kas.view_all', 'due.manage', 'submission.review_budget',
                'chat.use',
            ],
            RoleName::KoordinatorKajian->value => [
                'discussion.create', 'discussion.set_docs_link', 'discussion.manage_all',
                'aspiration.study', 'event.create_meeting',
                'chat.use',
                ...self::MANAGE_PROGRAMS,
            ],
            RoleName::AnggotaKajian->value => [
                'discussion.create',
                'chat.use',
            ],
            RoleName::KoordinatorMedpub->value => [
                'post.create', 'post.create_press_release', 'post.approve_coordinator', 'post.publish',
                'event.assign_task', 'event.create_meeting',
                'chat.use',
                ...self::MANAGE_PROGRAMS,
                ...self::MANAGE_GALLERIES,
            ],
            RoleName::AnggotaMedpub->value => [
                'post.create',
                'chat.use',
            ],
            RoleName::KoordinatorHumas->value => [
                'post.create_press_release', 'aspiration.handle', 'aspiration.escalate',
                'letter.create', 'event.create_ukm', 'event.create_meeting',
                'chat.use',
                ...self::MANAGE_PROGRAMS,
            ],
            RoleName::AnggotaHumas->value => [
                'post.create_press_release', 'aspiration.handle', 'letter.create',
                'chat.use',
            ],
            // Read-only: hanya lihat. Tidak ada chat. Tidak melihat User, Role, Log, Pengaturan, Struktur.
            RoleName::Pembina->value => [
                'view_any_division', 'view_division',
                'view_any_period', 'view_period',
                'view_any_download', 'view_download',
                'view_any_gallery', 'view_gallery',
                'view_any_program', 'view_program',
                'kas.view_all', 'report.view',
            ],
        ];
    }

    /** @return list<string> */
    public static function customPermissions(): array
    {
        return array_keys(config('filament-shield.custom_permissions', []));
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';
        $all = array_values(array_unique([...self::RESOURCE_PERMISSIONS, ...self::customPermissions()]));

        foreach ($all as $name) {
            Permission::findOrCreate($name, $guard);
        }

        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value, $guard);
        }

        Role::findByName(RoleName::SuperAdmin->value, $guard)->syncPermissions($all);

        foreach (self::rolePermissions() as $role => $permissions) {
            Role::findByName($role, $guard)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
