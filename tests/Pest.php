<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\DivisionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Semua test Feature memakai database SQLite in-memory (lihat phpunit.xml)
| yang di-refresh setiap test, plus role/permission & divisi bawaan.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->seed([DivisionSeeder::class, RolePermissionSeeder::class]);
        Filament::setCurrentPanel('admin');
    })
    ->in('Feature');

/** Membuat user aktif dengan satu role bawaan. */
function userWithRole(RoleName|string $role, array $attributes = []): User
{
    $roleName = $role instanceof RoleName ? $role->value : $role;

    $user = User::factory()->create($attributes);
    $user->assignRole(Role::findByName($roleName, 'web'));

    return $user;
}

function superAdmin(array $attributes = []): User
{
    return userWithRole(RoleName::SuperAdmin, $attributes);
}

/** @return list<RoleName> */
function nonSuperAdminRoles(): array
{
    return array_values(array_filter(
        RoleName::cases(),
        fn (RoleName $role): bool => $role !== RoleName::SuperAdmin,
    ));
}

/** Menguji komponen Livewire/Filament (pengganti plugin pest-plugin-livewire). */
function livewire(string $component, array $params = []): Testable
{
    return Livewire\Livewire::test($component, $params);
}
