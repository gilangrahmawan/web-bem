<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
| Aturan: sistem selalu menyisakan minimal satu Super Admin aktif.
*/

function accounts(): UserAccountService
{
    return app(UserAccountService::class);
}

it('menolak mencabut role super_admin dari satu-satunya Super Admin aktif', function (): void {
    $admin = superAdmin();

    expect(fn () => accounts()->syncRoles($admin, $admin, [RoleName::Sekretaris->value]))
        ->toThrow(BusinessRuleException::class, 'minimal satu Super Admin aktif');

    expect($admin->fresh()->isSuperAdmin())->toBeTrue();
});

it('Super Admin nonaktif tidak dihitung sebagai cadangan', function (): void {
    $admin = superAdmin();
    superAdmin(['is_active' => false]);

    expect(fn () => accounts()->syncRoles($admin, $admin, [RoleName::Sekretaris->value]))
        ->toThrow(BusinessRuleException::class, 'minimal satu Super Admin aktif');

    expect(User::query()->activeSuperAdmins()->count())->toBe(1);
});

it('mengizinkan mencabut role super_admin jika masih ada Super Admin aktif lain', function (): void {
    $ketua = superAdmin();
    $wakil = superAdmin();

    accounts()->syncRoles($ketua, $wakil, [RoleName::Sekretaris->value]);

    expect($wakil->fresh()->isSuperAdmin())->toBeFalse()
        ->and(User::query()->activeSuperAdmins()->count())->toBe(1);

    // Sekarang ketua satu-satunya: tidak boleh mencabut role dirinya sendiri.
    expect(fn () => accounts()->syncRoles($ketua, $ketua, [RoleName::Bendahara->value]))
        ->toThrow(BusinessRuleException::class);
});

it('satu-satunya Super Admin aktif tidak bisa dinonaktifkan', function (): void {
    $admin = superAdmin();

    expect(Gate::forUser($admin)->allows('deactivate', $admin))->toBeFalse();

    expect(fn () => accounts()->deactivate($admin, $admin))
        ->toThrow(BusinessRuleException::class);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('Super Admin bisa menonaktifkan Super Admin lain, lalu yang tersisa terkunci', function (): void {
    $ketua = superAdmin();
    $wakil = superAdmin();

    expect(Gate::forUser($ketua)->allows('deactivate', $wakil))->toBeTrue();
    accounts()->deactivate($ketua, $wakil);

    expect($wakil->fresh()->is_active)->toBeFalse()
        ->and(User::query()->activeSuperAdmins()->count())->toBe(1)
        ->and(Gate::forUser($ketua)->allows('deactivate', $ketua))->toBeFalse();
});

it('policy deactivate menolak jika target adalah Super Admin aktif terakhir', function (): void {
    $ketua = superAdmin();
    $wakil = superAdmin(['is_active' => false]);

    // Wakil nonaktif tidak bisa bertindak; dari sisi policy, ketua (satu-satunya yang aktif) terkunci.
    expect(Gate::forUser($wakil)->allows('deactivate', $ketua))->toBeFalse();
});

it('non-super-admin tidak bisa memberi, mencabut, atau menonaktifkan Super Admin', function (): void {
    $admin = superAdmin();
    superAdmin();

    $role = Role::findOrCreate('operator_user', 'web');
    $role->givePermissionTo(['user.manage', 'view_any_user', 'update_user']);
    $operator = User::factory()->create();
    $operator->assignRole($role);
    $target = userWithRole(RoleName::AnggotaKajian);

    expect(fn () => accounts()->syncRoles($operator, $target, [RoleName::SuperAdmin->value]))
        ->toThrow(BusinessRuleException::class, 'Hanya Super Admin');

    expect(fn () => accounts()->syncRoles($operator, $admin, [RoleName::Sekretaris->value]))
        ->toThrow(BusinessRuleException::class, 'Hanya Super Admin');

    expect(fn () => accounts()->deactivate($operator, $admin))
        ->toThrow(BusinessRuleException::class, 'Hanya Super Admin');

    expect(Gate::forUser($operator)->allows('update', $admin))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('deactivate', $admin))->toBeFalse()
        ->and(Gate::forUser($operator)->allows('update', $target))->toBeTrue();
});

it('form edit user menolak mencabut super_admin dari Super Admin aktif terakhir', function (): void {
    $admin = superAdmin();
    actingAs($admin);

    livewire(EditUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['roles' => [RoleName::Sekretaris->value]])
        ->call('save')
        ->assertNotified('Ditolak: sistem harus menyisakan minimal satu Super Admin aktif.');

    expect($admin->fresh()->isSuperAdmin())->toBeTrue();
});
