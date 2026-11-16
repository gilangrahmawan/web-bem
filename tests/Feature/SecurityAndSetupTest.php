<?php

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperAdminSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\get;

it('mengirim security headers', function (): void {
    get('/panel-bem/login')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
});

it('membuat 10 role bawaan dan semua permission kustom', function (): void {
    expect(Role::count())->toBe(11);

    foreach (RoleName::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }

    expect(RolePermissionSeeder::customPermissions())->toHaveCount(38);

    foreach (RolePermissionSeeder::customPermissions() as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }
});

it('nama permission resource sama dengan hasil discovery Filament Shield', function (): void {
    $discovered = collect(FilamentShield::getResources())
        ->flatMap(fn (array $resource): array => collect($resource['permissions'])->pluck('key')->all())
        ->sort()->values()->all();

    $seeded = collect(RolePermissionSeeder::RESOURCE_PERMISSIONS)->sort()->values()->all();

    expect($discovered)->toBe($seeded);
});

it('SuperAdminSeeder menolak berjalan selama .env masih placeholder', function (): void {
    config(['bem.superadmin' => [
        'name' => 'GANTI_NAMA_KETUA',
        'email' => 'GANTI_EMAIL@contoh.test',
        'password' => 'GANTI_PASSWORD',
    ]]);

    expect(fn () => $this->seed(SuperAdminSeeder::class))->toThrow(RuntimeException::class, 'placeholder');
    expect(User::count())->toBe(0);
});

it('SuperAdminSeeder dilewati bila SUPERADMIN_* kosong (produksi memakai bem:create-admin)', function (): void {
    config(['bem.superadmin' => ['name' => null, 'email' => '', 'password' => null]]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::count())->toBe(0);
});

it('SuperAdminSeeder membuat Ketua yang wajib ganti password', function (): void {
    config(['bem.superadmin' => [
        'name' => 'Ketua BEM',
        'email' => 'ketua@ubhinus.test',
        'password' => 'Ketua#BEM2026',
    ]]);

    $this->seed(SuperAdminSeeder::class);

    $ketua = User::where('email', 'ketua@ubhinus.test')->first();

    expect($ketua->isSuperAdmin())->toBeTrue()
        ->and($ketua->must_change_password)->toBeTrue()
        ->and($ketua->is_active)->toBeTrue();
});

it('DemoSeeder menolak berjalan di luar lingkungan lokal', function (): void {
    expect(app()->environment())->toBe('testing');
    expect(fn () => $this->seed(DemoSeeder::class))->toThrow(RuntimeException::class, 'APP_ENV=local');
});

it('SettingSeeder mengisi nominal kas default Rp5.000 tanpa menimpa perubahan', function (): void {
    $this->seed(SettingSeeder::class);
    expect(Setting::get('kas_weekly_amount'))->toBe(5000);

    Setting::set('kas_weekly_amount', 7000);
    $this->seed(SettingSeeder::class);

    expect(Setting::get('kas_weekly_amount'))->toBe(7000);
});
