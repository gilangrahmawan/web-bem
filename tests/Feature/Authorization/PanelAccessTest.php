<?php

use App\Enums\RoleName;
use App\Models\Division;
use App\Models\Period;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

$superAdminOnlyPages = [
    'user' => '/panel-bem/users',
    'user baru' => '/panel-bem/users/create',
    'role' => '/panel-bem/shield/roles',
    'log aktivitas' => '/panel-bem/activity-logs',
    'pengaturan' => '/panel-bem/pengaturan',
];

it('hanya super_admin yang bisa membuka halaman User, Role, Log, dan Pengaturan', function (string $url): void {
    actingAs(superAdmin())->get($url)->assertOk();

    foreach (nonSuperAdminRoles() as $role) {
        actingAs(userWithRole($role))->get($url)->assertForbidden();
    }
})->with($superAdminOnlyPages);

it('super_admin bisa membuka Divisi dan Periode', function (): void {
    $admin = superAdmin();

    actingAs($admin)->get('/panel-bem/divisions')->assertOk();
    actingAs($admin)->get('/panel-bem/divisions/create')->assertOk();
    actingAs($admin)->get('/panel-bem/periods')->assertOk();
    actingAs($admin)->get('/panel-bem/periods/create')->assertOk();
});

it('pembina hanya bisa melihat Divisi dan Periode', function (): void {
    $pembina = userWithRole(RoleName::Pembina);
    $division = Division::first();
    $period = Period::factory()->create();

    actingAs($pembina)->get('/panel-bem/divisions')->assertOk();
    actingAs($pembina)->get('/panel-bem/periods')->assertOk();

    actingAs($pembina)->get('/panel-bem/divisions/create')->assertForbidden();
    actingAs($pembina)->get("/panel-bem/divisions/{$division->id}/edit")->assertForbidden();
    actingAs($pembina)->get('/panel-bem/periods/create')->assertForbidden();
    actingAs($pembina)->get("/panel-bem/periods/{$period->id}/edit")->assertForbidden();
});

it('pembina tidak bisa mengubah apa pun', function (): void {
    $pembina = userWithRole(RoleName::Pembina);
    $other = userWithRole(RoleName::AnggotaKajian);
    $division = Division::first();
    $period = Period::factory()->create();
    $role = Role::findByName(RoleName::AnggotaKajian->value);

    expect($pembina->can('create', Division::class))->toBeFalse()
        ->and($pembina->can('update', $division))->toBeFalse()
        ->and($pembina->can('delete', $division))->toBeFalse()
        ->and($pembina->can('create', Period::class))->toBeFalse()
        ->and($pembina->can('update', $period))->toBeFalse()
        ->and($pembina->can('delete', $period))->toBeFalse()
        ->and($pembina->can('activate', $period))->toBeFalse()
        ->and($pembina->can('viewAny', User::class))->toBeFalse()
        ->and($pembina->can('create', User::class))->toBeFalse()
        ->and($pembina->can('update', $other))->toBeFalse()
        ->and($pembina->can('deactivate', $other))->toBeFalse()
        ->and($pembina->can('resetPassword', $other))->toBeFalse()
        ->and($pembina->can('viewAny', Role::class))->toBeFalse()
        ->and($pembina->can('create', Role::class))->toBeFalse()
        ->and($pembina->can('update', $role))->toBeFalse()
        ->and($pembina->can('setting.manage'))->toBeFalse()
        ->and($pembina->can('chat.use'))->toBeFalse();

    // Semua permission pembina hanya bertipe lihat (view/view_any) atau baca laporan/kas.
    $pembina->getAllPermissions()->pluck('name')->each(
        fn (string $name) => expect($name)->toMatch('/^(view_|view_any_|report\.view|kas\.view_all)/')
    );
});

it('role super_admin terkunci: tidak bisa diedit atau dihapus, bahkan oleh super_admin', function (): void {
    $admin = superAdmin();
    $locked = Role::findByName(RoleName::SuperAdmin->value);
    $normal = Role::findByName(RoleName::AnggotaMedpub->value);

    expect(Gate::forUser($admin)->allows('update', $locked))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $locked))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('deleteAny', Role::class))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('update', $normal))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $normal))->toBeTrue();

    actingAs($admin)->get("/panel-bem/shield/roles/{$locked->id}/edit")->assertForbidden();
    actingAs($admin)->get("/panel-bem/shield/roles/{$normal->id}/edit")->assertOk();
});

it('user tidak bisa dihapus, bahkan oleh super_admin', function (): void {
    $admin = superAdmin();
    $user = userWithRole(RoleName::AnggotaHumas);

    expect(Gate::forUser($admin)->allows('delete', $user))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('forceDelete', $user))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('deleteAny', User::class))->toBeFalse();
});

it('divisi yang masih punya anggota dan periode aktif tidak bisa dihapus', function (): void {
    $admin = superAdmin();
    $bph = Division::where('slug', 'bph')->first();
    userWithRole(RoleName::Sekretaris, ['division_id' => $bph->id]);
    $empty = Division::factory()->create();
    $active = Period::factory()->active()->create();
    $inactive = Period::factory()->create();

    expect(Gate::forUser($admin)->allows('delete', $bph))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $empty))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $active->fresh()))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $inactive))->toBeTrue();
});

it('menu tanpa izin tidak tampil di navigasi', function (): void {
    actingAs(userWithRole(RoleName::AnggotaKajian))
        ->get('/panel-bem')
        ->assertOk()
        ->assertDontSee('Manajemen User')
        ->assertDontSee('Role &amp; Permission', false)
        ->assertDontSee('Log Aktivitas')
        ->assertDontSee('Pengaturan Website');
});
