<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migration DATA (hanya menambah, keputusan Fase 11A): `form.manage` →
 * super_admin, sekretaris (semua form), koordinator kajian/medpub/humas (form divisinya).
 */
return new class extends Migration
{
    private const PERMISSION = 'form.manage';

    private const ROLES = ['super_admin', 'sekretaris', 'koordinator_kajian', 'koordinator_medpub', 'koordinator_humas'];

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
