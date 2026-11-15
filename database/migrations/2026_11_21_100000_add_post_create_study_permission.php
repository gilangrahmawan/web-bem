<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration DATA (hanya menambah): permission `post.create_study` (buat draft Kajian Publik)
 * untuk koordinator_kajian, anggota_kajian, dan super_admin (keputusan pemilik Fase 6).
 * givePermissionTo, bukan sync: permission lain & perubahan lewat Shield tidak tersentuh.
 */
return new class extends Migration
{
    private const PERMISSION = 'post.create_study';

    private const ROLES = ['super_admin', 'koordinator_kajian', 'anggota_kajian'];

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
