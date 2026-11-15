<?php

use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\User;
use App\Services\UserAccountService;
use Database\Factories\UserFactory;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use function Pest\Laravel\actingAs;

it('super_admin membuat akun pengurus baru yang wajib ganti password', function (): void {
    actingAs(superAdmin());
    $kajian = Division::where('slug', 'kajian')->first();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Budi Santoso',
            'email' => 'budi@ubhinus.test',
            'phone' => '081234567890',
            'division_id' => $kajian->id,
            'position' => 'Anggota Kajian',
            'roles' => [RoleName::AnggotaKajian->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'budi@ubhinus.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasRole(RoleName::AnggotaKajian->value))->toBeTrue()
        ->and($user->division_id)->toBe($kajian->id)
        ->and(ActivityLog::where('action', 'user.created')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('role wajib diisi saat membuat user', function (): void {
    actingAs(superAdmin());

    livewire(CreateUser::class)
        ->fillForm(['name' => 'Tanpa Role', 'email' => 'tanparole@ubhinus.test', 'roles' => []])
        ->call('create')
        ->assertHasFormErrors(['roles']);
});

it('reset password membuat password sementara dan mewajibkan ganti password', function (): void {
    actingAs(superAdmin());
    $user = userWithRole(RoleName::Bendahara);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('resetPassword')->table($user))
        ->assertNotified();

    $user->refresh();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check(UserFactory::PASSWORD, $user->password))->toBeFalse()
        ->and(ActivityLog::where('action', 'user.password_reset')->exists())->toBeTrue();
});

it('menonaktifkan dan mengaktifkan kembali user', function (): void {
    actingAs(superAdmin());
    $user = userWithRole(RoleName::AnggotaMedpub);

    livewire(ListUsers::class)->callAction(TestAction::make('deactivate')->table($user));
    expect($user->fresh()->is_active)->toBeFalse();

    livewire(ListUsers::class)->callAction(TestAction::make('activate')->table($user));
    expect($user->fresh()->is_active)->toBeTrue();

    expect(ActivityLog::whereIn('action', ['user.deactivated', 'user.activated'])->count())->toBe(2);
});

it('password sementara memenuhi aturan password', function (): void {
    $service = app(UserAccountService::class);

    foreach (range(1, 20) as $_) {
        $password = $service->generateTemporaryPassword();

        expect(validator(['p' => $password], ['p' => [Password::default()]])->passes())
            ->toBeTrue();
    }
});
