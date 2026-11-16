<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration DATA (hanya menambah, keputusan Fase 7B):
 * - `recruitment.interview` → koordinator_kajian, koordinator_medpub, koordinator_humas, super_admin;
 * - setting `recruitment_retention_months` = 6 (bila belum ada).
 */
return new class extends Migration
{
    private const PERMISSION = 'recruitment.interview';

    private const ROLES = ['super_admin', 'koordinator_kajian', 'koordinator_medpub', 'koordinator_humas'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        foreach (self::ROLES as $name) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();

            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        if (! DB::table('settings')->where('key', 'recruitment_retention_months')->exists()) {
            DB::table('settings')->insert([
                'key' => 'recruitment_retention_months',
                'value' => json_encode(6),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
        DB::table('settings')->where('key', 'recruitment_retention_months')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
