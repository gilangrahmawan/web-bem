<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration DATA (hanya menambah, keputusan Fase 7):
 * - role `pengaju_kegiatan` (bukan pengurus BEM);
 * - `activity.submit` → pengurus BEM kecuali pembina, dan pengaju_kegiatan;
 * - `applicant.manage` → super_admin, sekretaris;
 * - setting `lpj_deadline_days` = 14 (bila belum ada).
 * givePermissionTo, bukan sync: permission lain & perubahan lewat Shield tidak tersentuh.
 */
return new class extends Migration
{
    private const GRANTS = [
        'activity.submit' => [
            'super_admin', 'sekretaris', 'bendahara',
            'koordinator_kajian', 'anggota_kajian', 'koordinator_medpub', 'anggota_medpub',
            'koordinator_humas', 'anggota_humas', 'pengaju_kegiatan',
        ],
        'applicant.manage' => ['super_admin', 'sekretaris'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Role baru dibuat hanya bila tabel role sudah terisi (database berjalan); instalasi baru → RolePermissionSeeder.
        if (Role::query()->exists()) {
            Role::findOrCreate('pengaju_kegiatan', 'web');
        }

        foreach (self::GRANTS as $permissionName => $roles) {
            $permission = Permission::findOrCreate($permissionName, 'web');

            foreach ($roles as $name) {
                $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

                if ($role && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        if (! DB::table('settings')->where('key', 'lpj_deadline_days')->exists()) {
            DB::table('settings')->insert([
                'key' => 'lpj_deadline_days',
                'value' => json_encode(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::GRANTS))->where('guard_name', 'web')->delete();
        Role::query()->where('name', 'pengaju_kegiatan')->where('guard_name', 'web')->delete();
        DB::table('settings')->where('key', 'lpj_deadline_days')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
