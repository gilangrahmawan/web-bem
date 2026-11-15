<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration DATA (hanya menambah): permission `aspiration.view` (lihat aspirasi, AGENTS.md D3 "L")
 * untuk sekretaris, anggota_kajian, pembina, dan super_admin.
 *
 * Sengaja memakai givePermissionTo (bukan sync) sehingga permission lain & perubahan role lewat
 * Shield tidak tersentuh. Role yang belum ada (database baru) dilewati; RolePermissionSeeder
 * mengurus instalasi baru.
 */
return new class extends Migration
{
    private const PERMISSION = 'aspiration.view';

    private const ROLES = ['super_admin', 'sekretaris', 'anggota_kajian', 'pembina'];

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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
