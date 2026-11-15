<?php

use App\Enums\RoleName;
use App\Filament\Auth\Login;
use App\Models\ActivityLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\get;

beforeEach(function (): void {
    RateLimiter::clear('panel-login:'.sha1('|127.0.0.1'));
});

function attemptLogin(string $email, string $password)
{
    return livewire(Login::class)
        ->set('data.email', $email)
        ->set('data.password', $password)
        ->call('authenticate');
}

it('mengizinkan pengurus aktif login dan mencatat log', function (): void {
    $user = userWithRole(RoleName::Sekretaris);

    attemptLogin($user->email, UserFactory::PASSWORD)->assertHasNoErrors();

    expect(auth()->id())->toBe($user->id)
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and(ActivityLog::where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('menolak login user nonaktif', function (): void {
    $user = userWithRole(RoleName::Sekretaris, ['is_active' => false]);

    attemptLogin($user->email, UserFactory::PASSWORD)->assertHasErrors(['data.email']);

    expect(auth()->check())->toBeFalse()
        ->and(ActivityLog::where('action', 'auth.failed')->exists())->toBeTrue();
});

it('menolak login user tanpa role', function (): void {
    $user = User::factory()->create();

    attemptLogin($user->email, UserFactory::PASSWORD)->assertHasErrors(['data.email']);

    expect(auth()->check())->toBeFalse();
});

it('mencatat gagal login tanpa menyimpan password', function (): void {
    $user = userWithRole(RoleName::Bendahara);

    attemptLogin($user->email, 'password-salah-1!')->assertHasErrors(['data.email']);

    $log = ActivityLog::where('action', 'auth.failed')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['email'])->toBe($user->email)
        ->and(json_encode($log->properties))->not->toContain('password-salah-1!');
});

it('mengunci login 15 menit setelah 5 kali gagal, walau password benar', function (): void {
    $user = userWithRole(RoleName::Bendahara);

    foreach (range(1, 5) as $_) {
        attemptLogin($user->email, 'password-salah-1!')->assertHasErrors(['data.email']);
    }

    attemptLogin($user->email, UserFactory::PASSWORD)
        ->assertHasErrors(['data.email'])
        ->assertSee('dikunci sementara');

    expect(auth()->check())->toBeFalse()
        ->and(ActivityLog::where('action', 'auth.lockout')->exists())->toBeTrue();

    $this->travel(16)->minutes();

    attemptLogin($user->email, UserFactory::PASSWORD)->assertHasNoErrors();
    expect(auth()->id())->toBe($user->id);
});

it('tidak menyediakan halaman registrasi maupun reset password mandiri', function (): void {
    get('/panel-bem/register')->assertNotFound();
    get('/panel-bem/password-reset/request')->assertNotFound();
});

it('tidak menampilkan opsi ingat saya', function (): void {
    get('/panel-bem/login')->assertOk()->assertDontSee('data.remember');
});
