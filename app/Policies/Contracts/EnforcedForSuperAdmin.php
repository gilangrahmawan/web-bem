<?php

namespace App\Policies\Contracts;

/**
 * Policy yang punya aturan keras yang juga berlaku bagi super_admin.
 *
 * Gate::before biasanya meloloskan super_admin untuk semua aksi. Untuk aksi yang
 * dikembalikan method ini, Gate::before tidak ikut campur sehingga method policy
 * yang menentukan (policy tersebut tetap boleh meloloskan super_admin secara eksplisit).
 */
interface EnforcedForSuperAdmin
{
    /** @return list<string> */
    public static function superAdminEnforcedAbilities(): array;
}
