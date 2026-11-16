<?php

use App\Enums\RoleName;
use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\MyProfile;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function setupUrl(): string
{
    return Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl();
}

it('Ketua/Wakil, Sekretaris, Bendahara tanpa 2FA diarahkan ke pengaturan 2FA (halaman & route berkas)', function (RoleName $role): void {
    $user = member($role, ['two_factor_secret' => null]);

    actingAs($user);
    get('/panel-bem')->assertRedirect(setupUrl());
    get('/panel-bem/kas-mingguan')->assertRedirect(setupUrl());
    get(route('panel.chat.attachment', 999))->assertRedirect(setupUrl());
    get(setupUrl())->assertOk()->assertSee('authenticator', false);

    $user->forceFill(['two_factor_secret' => app(AppAuthentication::class)->generateSecret()])->save();
    get('/panel-bem')->assertOk();
})->with([RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::Bendahara]);

it('role lain tidak wajib 2FA (boleh sukarela dari Profil Saya)', function (RoleName $role): void {
    actingAs(member($role));

    get('/panel-bem')->assertOk();
    get(MyProfile::getUrl())->assertOk()->assertSee('Autentikasi dua langkah (2FA)')->assertSee('Opsional');
})->with([RoleName::AnggotaKajian, RoleName::KoordinatorHumas, RoleName::Pembina, RoleName::PengajuKegiatan]);

it('akun baru: ganti password dulu, baru diminta mengatur 2FA', function (): void {
    $ketua = member(RoleName::SuperAdmin, ['two_factor_secret' => null, 'must_change_password' => true]);

    actingAs($ketua);
    get('/panel-bem')->assertRedirect(ChangePassword::getUrl());
    get(ChangePassword::getUrl())->assertOk();

    $ketua->forceFill(['must_change_password' => false])->save();
    get('/panel-bem')->assertRedirect(setupUrl());
});

it('kode TOTP & kode pemulihan diverifikasi; kode pemulihan hanya sekali pakai', function (): void {
    $provider = AppAuthentication::make()->recoverable();
    $user = member(RoleName::Sekretaris);
    $secret = $provider->generateSecret();
    $user->saveAppAuthenticationSecret($secret);
    $codes = $provider->generateRecoveryCodes();
    $provider->saveRecoveryCodes($user, $codes);

    expect($provider->verifyCode($provider->getCurrentCode($user, $secret), $secret))->toBeTrue()
        ->and($provider->verifyCode('000000', $secret))->toBeFalse()
        ->and($provider->verifyRecoveryCode($codes[0], $user->fresh()))->toBeTrue()
        ->and($provider->verifyRecoveryCode($codes[0], $user->fresh()))->toBeFalse();

    // Tersimpan terenkripsi.
    $raw = DB::table('users')->where('id', $user->id)->first();
    expect($raw->two_factor_secret)->not->toBe($secret)->and($raw->two_factor_recovery_codes)->not->toContain($codes[1]);
});

it('Reset 2FA: hanya Super Admin, bukan untuk diri sendiri; dicatat & session diakhiri; perintah darurat tersedia', function (): void {
    $admin = superAdmin();
    $bendahara = member(RoleName::Bendahara);
    $sekretaris = member(RoleName::Sekretaris);

    expect(Gate::forUser($admin)->allows('resetTwoFactor', $bendahara))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('resetTwoFactor', $admin))->toBeFalse()
        ->and(Gate::forUser($sekretaris)->allows('resetTwoFactor', $bendahara))->toBeFalse();

    app(UserAccountService::class)->resetTwoFactor($admin, $bendahara);
    expect($bendahara->fresh()->two_factor_secret)->toBeNull()
        ->and(ActivityLog::where('action', 'user.two_factor_reset')->where('subject_id', $bendahara->id)->exists())->toBeTrue();

    $this->artisan('bem:reset-2fa', ['email' => $sekretaris->email])->expectsConfirmation(
        "Reset 2FA untuk {$sekretaris->name} ({$sekretaris->email})? Semua session akun ini diakhiri.", 'yes',
    )->assertSuccessful();
    expect($sekretaris->fresh()->two_factor_secret)->toBeNull();
});

it('bem:create-admin membuat Super Admin aktif yang wajib ganti password; password lemah ditolak', function (): void {
    $this->artisan('bem:create-admin', ['--name' => 'Ketua Produksi', '--email' => 'ketua@ubhinus.test'])
        ->expectsQuestion('Password sementara (min. 10 karakter, huruf, angka, simbol; tidak ditampilkan)', 'lemah')
        ->expectsQuestion('Ulangi password', 'lemah')
        ->assertFailed();
    expect(User::where('email', 'ketua@ubhinus.test')->exists())->toBeFalse();

    $this->artisan('bem:create-admin', ['--name' => 'Ketua Produksi', '--email' => 'Ketua@Ubhinus.test'])
        ->expectsQuestion('Password sementara (min. 10 karakter, huruf, angka, simbol; tidak ditampilkan)', 'Rahasia#2027x')
        ->expectsQuestion('Ulangi password', 'Rahasia#2027x')
        ->assertSuccessful();

    $ketua = User::where('email', 'ketua@ubhinus.test')->sole();
    expect($ketua->isSuperAdmin())->toBeTrue()->and($ketua->must_change_password)->toBeTrue()->and($ketua->is_active)->toBeTrue()
        ->and(Hash::check('Rahasia#2027x', $ketua->password))->toBeTrue()
        ->and($ketua->requiresTwoFactor())->toBeTrue()->and($ketua->hasTwoFactorEnabled())->toBeFalse();
});
