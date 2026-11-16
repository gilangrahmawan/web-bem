<?php

use App\Enums\RoleName;
use App\Filament\Pages\MyProfile;
use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\Period;
use App\Models\StructureMember;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('public'));

it('semua role, termasuk pembina, bisa membuka Profil Saya', function (RoleName $role): void {
    actingAs(member($role))->get('/panel-bem/profil-saya')
        ->assertOk()
        ->assertSee('Profil Saya')
        ->assertSee('Gunakan foto formal/rapi');
})->with(RoleName::cases());

it('user nonaktif dan user yang wajib ganti password tidak bisa membuka Profil Saya', function (): void {
    actingAs(userWithRole(RoleName::AnggotaKajian, ['must_change_password' => true]))
        ->get('/panel-bem/profil-saya')
        ->assertRedirect('/panel-bem/ganti-password');

    actingAs(userWithRole(RoleName::AnggotaKajian, ['is_active' => false]))
        ->get('/panel-bem/profil-saya')
        ->assertRedirect('/panel-bem/login');
});

it('mengubah nomor HP sendiri dan mencatatnya tanpa menyimpan nomornya di log', function (): void {
    $user = userWithRole(RoleName::Bendahara, ['phone' => '081111111111']);
    actingAs($user);

    livewire(MyProfile::class)
        ->fillForm(['phone' => '082222222222'], 'profileForm')
        ->call('saveProfile')
        ->assertHasNoFormErrors(form: 'profileForm');

    expect($user->fresh()->phone)->toBe('082222222222');

    $log = ActivityLog::where('action', 'profile.phone_updated')->where('user_id', $user->id)->first();
    expect($log)->not->toBeNull()
        ->and(json_encode($log->properties))->not->toContain('082222222222')
        ->and(json_encode($log->properties))->not->toContain('081111111111');
});

it('menolak nomor HP yang formatnya tidak valid', function (): void {
    actingAs(userWithRole(RoleName::Bendahara));

    livewire(MyProfile::class)
        ->fillForm(['phone' => '<script>'], 'profileForm')
        ->call('saveProfile')
        ->assertHasFormErrors(['phone'], 'profileForm');
});

it('upload foto profil disimpan sebagai WebP bernama UUID dan foto lama dihapus', function (): void {
    Storage::disk('public')->put('users/lama.webp', 'lama');
    $user = userWithRole(RoleName::AnggotaMedpub, ['photo' => 'users/lama.webp']);
    actingAs($user);

    // Seperti di browser: foto lama dilepas dulu, lalu foto baru diunggah.
    livewire(MyProfile::class)
        ->set('profileData.photo', [])
        ->fillForm(['photo' => UploadedFile::fake()->image('selfie.jpg', 900, 900)], 'profileForm')
        ->call('saveProfile')
        ->assertHasNoFormErrors(form: 'profileForm');

    $photo = $user->fresh()->photo;
    expect($photo)->toMatch('#^users/[0-9a-f-]{36}\.webp$#');
    Storage::disk('public')->assertExists($photo);
    Storage::disk('public')->assertMissing('users/lama.webp');
    expect(ActivityLog::where('action', 'profile.photo_updated')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('menolak foto yang bukan gambar atau lebih dari 2 MB', function (UploadedFile $file): void {
    $user = userWithRole(RoleName::AnggotaMedpub);
    actingAs($user);

    livewire(MyProfile::class)
        ->fillForm(['photo' => $file], 'profileForm')
        ->call('saveProfile')
        ->assertHasFormErrors(['photo'], 'profileForm');

    expect($user->fresh()->photo)->toBeNull();
})->with([
    'bukan gambar' => fn () => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    'lebih dari 2 MB' => fn () => UploadedFile::fake()->image('besar.jpg', 800, 800)->size(3000),
]);

it('tidak bisa mengubah nama, email, role, divisi, atau status walau state dimanipulasi', function (): void {
    $kajian = Division::where('slug', 'kajian')->first();
    $humas = Division::where('slug', 'humas')->first();
    $user = userWithRole(RoleName::AnggotaKajian, [
        'name' => 'Nama Asli',
        'email' => 'asli@ubhinus.test',
        'division_id' => $kajian->id,
    ]);
    actingAs($user);

    livewire(MyProfile::class)
        ->set('profileData.name', 'Nama Palsu')
        ->set('profileData.email', 'palsu@ubhinus.test')
        ->set('profileData.division_id', $humas->id)
        ->set('profileData.roles', [RoleName::SuperAdmin->value])
        ->set('profileData.is_active', false)
        ->set('profileData.must_change_password', true)
        ->set('profileData.phone', '083333333333')
        ->call('saveProfile');

    $user->refresh();

    expect($user->name)->toBe('Nama Asli')
        ->and($user->email)->toBe('asli@ubhinus.test')
        ->and($user->division_id)->toBe($kajian->id)
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->getRoleNames()->all())->toBe([RoleName::AnggotaKajian->value])
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->phone)->toBe('083333333333');
});

it('hanya mengubah profil milik sendiri, akun lain tidak tersentuh', function (): void {
    $me = userWithRole(RoleName::AnggotaHumas, ['phone' => '081000000001']);
    $other = userWithRole(RoleName::AnggotaHumas, ['phone' => '081000000002']);
    actingAs($me);

    livewire(MyProfile::class)
        ->set('profileData.id', $other->id)
        ->set('profileData.user_id', $other->id)
        ->fillForm(['phone' => '089999999999'], 'profileForm')
        ->call('saveProfile');

    expect($me->fresh()->phone)->toBe('089999999999')
        ->and($other->fresh()->phone)->toBe('081000000002');
});

it('ganti password dari Profil Saya mengakhiri session lain', function (): void {
    config(['session.driver' => 'database']);
    $user = userWithRole(RoleName::Pembina);
    DB::table('sessions')->insert([
        'id' => 'perangkat-lain', 'user_id' => $user->id, 'ip_address' => '10.0.0.9',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);
    actingAs($user);

    livewire(MyProfile::class)
        ->fillForm([
            'current_password' => UserFactory::PASSWORD,
            'password' => 'Pembina#Baru2026',
            'password_confirmation' => 'Pembina#Baru2026',
        ], 'passwordForm')
        ->call('savePassword')
        ->assertHasNoFormErrors(form: 'passwordForm');

    expect(Hash::check('Pembina#Baru2026', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'perangkat-lain')->exists())->toBeFalse()
        ->and(ActivityLog::where('action', 'auth.password_changed')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('ganti password ditolak jika password lama salah atau password baru lemah', function (array $input, string $errorField): void {
    $user = userWithRole(RoleName::Sekretaris);
    actingAs($user);

    livewire(MyProfile::class)
        ->fillForm($input, 'passwordForm')
        ->call('savePassword')
        ->assertHasFormErrors([$errorField], 'passwordForm');

    expect(Hash::check(UserFactory::PASSWORD, $user->fresh()->password))->toBeTrue();
})->with([
    'password lama salah' => [['current_password' => 'Salah#12345', 'password' => 'Baru#Kuat2026', 'password_confirmation' => 'Baru#Kuat2026'], 'current_password'],
    'password baru lemah' => [['current_password' => UserFactory::PASSWORD, 'password' => 'lemah', 'password_confirmation' => 'lemah'], 'password'],
    'konfirmasi beda' => [['current_password' => UserFactory::PASSWORD, 'password' => 'Baru#Kuat2026', 'password_confirmation' => 'Beda#Kuat2026'], 'password_confirmation'],
]);

describe('foto di halaman publik Struktur', function (): void {
    beforeEach(function (): void {
        $this->period = Period::factory()->active()->create();
        $this->division = Division::where('slug', 'kajian')->first();
    });

    it('memakai foto profil akun jika foto struktur kosong', function (): void {
        $user = User::factory()->create(['photo' => 'users/profil-akun.webp']);
        StructureMember::factory()->create([
            'period_id' => $this->period->id, 'division_id' => $this->division->id,
            'user_id' => $user->id, 'name' => 'Anggota Bertaut', 'photo' => null,
        ]);

        get('/struktur')->assertOk()->assertSee('users/profil-akun.webp');
        get('/divisi/kajian')->assertOk()->assertSee('users/profil-akun.webp');
    });

    it('foto struktur diutamakan jika keduanya ada', function (): void {
        $user = User::factory()->create(['photo' => 'users/profil-akun.webp']);
        StructureMember::factory()->create([
            'period_id' => $this->period->id, 'division_id' => $this->division->id,
            'user_id' => $user->id, 'photo' => 'structure/foto-resmi.webp',
        ]);

        get('/struktur')->assertSee('structure/foto-resmi.webp')->assertDontSee('users/profil-akun.webp');
    });

    it('memakai avatar inisial jika tidak ada foto sama sekali', function (): void {
        StructureMember::factory()->create([
            'period_id' => $this->period->id, 'division_id' => $this->division->id,
            'user_id' => null, 'name' => 'Tanpa Foto', 'photo' => null,
        ]);

        get('/struktur')->assertSee('data:image/svg+xml;base64', false);
    });
});
