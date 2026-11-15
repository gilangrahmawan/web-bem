<?php

use App\Enums\RoleName;
use App\Filament\Pages\ChangePassword;
use App\Models\ActivityLog;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('mengarahkan user yang wajib ganti password ke halaman ganti password', function (): void {
    $user = userWithRole(RoleName::AnggotaKajian, ['must_change_password' => true]);

    actingAs($user)->get('/panel-bem')->assertRedirect('/panel-bem/ganti-password');
    actingAs($user)->get('/panel-bem/ganti-password')->assertOk();
});

it('user yang sudah ganti password bisa membuka dashboard', function (): void {
    $user = userWithRole(RoleName::AnggotaKajian);

    actingAs($user)->get('/panel-bem')->assertOk();
});

it('mengganti password, mencabut kewajiban ganti, dan mengakhiri session lain', function (): void {
    config(['session.driver' => 'database']);

    $user = userWithRole(RoleName::AnggotaHumas, ['must_change_password' => true]);

    DB::table('sessions')->insert([
        'id' => 'session-perangkat-lain',
        'user_id' => $user->id,
        'ip_address' => '10.0.0.2',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    actingAs($user);

    livewire(ChangePassword::class)
        ->fillForm([
            'current_password' => UserFactory::PASSWORD,
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect('/panel-bem');

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('PasswordBaru#2026', $user->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'session-perangkat-lain')->exists())->toBeFalse()
        ->and(ActivityLog::where('action', 'auth.password_changed')->exists())->toBeTrue();
});

it('menolak password baru yang tidak memenuhi aturan', function (string $password): void {
    $user = userWithRole(RoleName::AnggotaHumas, ['must_change_password' => true]);
    actingAs($user);

    livewire(ChangePassword::class)
        ->fillForm([
            'current_password' => UserFactory::PASSWORD,
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect($user->fresh()->must_change_password)->toBeTrue();
})->with([
    'terlalu pendek' => 'Ab1!xyz',
    'tanpa simbol' => 'PasswordBaru2026',
    'tanpa angka' => 'PasswordBaru#!',
    'tanpa huruf' => '1234567890!!',
    'sama dengan lama' => UserFactory::PASSWORD,
]);

it('menolak jika password saat ini salah', function (): void {
    $user = userWithRole(RoleName::AnggotaHumas, ['must_change_password' => true]);
    actingAs($user);

    livewire(ChangePassword::class)
        ->fillForm([
            'current_password' => 'Salah#12345',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])
        ->call('save')
        ->assertHasFormErrors(['current_password']);
});

it('mengeluarkan user yang dinonaktifkan saat sedang login', function (): void {
    $user = userWithRole(RoleName::Sekretaris);
    actingAs($user)->get('/panel-bem')->assertOk();

    $user->forceFill(['is_active' => false])->save();

    actingAs($user)->get('/panel-bem')->assertRedirect('/panel-bem/login');
    expect(auth()->check())->toBeFalse();
});
