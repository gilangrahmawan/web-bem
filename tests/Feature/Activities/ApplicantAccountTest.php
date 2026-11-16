<?php

use App\Enums\RoleName;
use App\Filament\Resources\ApplicantAccounts\Pages\CreateApplicantAccount;
use App\Filament\Resources\ApplicantAccounts\Pages\EditApplicantAccount;
use App\Filament\Resources\ApplicantAccounts\Pages\ListApplicantAccounts;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Organization;
use App\Models\User;
use App\Services\ApplicantAccountService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

it('Sekretaris membuat akun pengaju: role dipaksa pengaju_kegiatan dan wajib ganti password', function (): void {
    $org = Organization::factory()->create(['name' => 'UKM Futsal']);
    actingAs(member(RoleName::Sekretaris));

    livewire(CreateApplicantAccount::class)
        ->fillForm(['name' => 'Budi Futsal', 'email' => 'budi@futsal.test', 'organization_id' => $org->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'budi@futsal.test')->sole();

    expect($user->getRoleNames()->all())->toBe(['pengaju_kegiatan'])
        ->and($user->organization_id)->toBe($org->id)
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->division_id)->toBeNull();
});

it('manipulasi request: role/divisi tambahan diabaikan, organisasi BEM atau nonaktif ditolak', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $service = app(ApplicantAccountService::class);

    [$user] = $service->create($sekretaris, [
        'name' => 'Penyusup', 'email' => 'penyusup@test.test', 'organization_id' => Organization::factory()->create()->id,
        'roles' => ['super_admin'], 'division_id' => divisionId('bph'), 'is_active' => false, 'must_change_password' => false,
    ]);

    expect($user->getRoleNames()->all())->toBe(['pengaju_kegiatan'])
        ->and($user->division_id)->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();

    expect(fn () => $service->create($sekretaris, ['name' => 'X', 'email' => 'x@t.test', 'organization_id' => Organization::bem()->id]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->create($sekretaris, ['name' => 'Y', 'email' => 'y@t.test', 'organization_id' => Organization::factory()->create(['is_active' => false])->id]))
        ->toThrow(ValidationException::class);
});

it('Sekretaris tidak bisa melihat atau mengubah akun pengurus BEM lewat jalur akun pengaju', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $pengurus = member(RoleName::AnggotaMedpub, ['name' => 'Pengurus Medpub']);
    $campuran = member(RoleName::PengajuKegiatan);
    $campuran->assignRole('anggota_humas'); // akun bukan murni pengaju
    $service = app(ApplicantAccountService::class);

    actingAs($sekretaris)->get("/panel-bem/akun-pengaju/{$pengurus->id}/edit")->assertNotFound();
    actingAs($sekretaris)->get("/panel-bem/akun-pengaju/{$campuran->id}/edit")->assertNotFound();

    actingAs($sekretaris);
    livewire(ListApplicantAccounts::class)->assertCanNotSeeTableRecords([$pengurus, $campuran]);

    foreach ([
        fn () => $service->update($sekretaris, $pengurus, ['name' => 'Diubah', 'email' => $pengurus->email, 'organization_id' => Organization::factory()->create()->id]),
        fn () => $service->resetPassword($sekretaris, $pengurus),
        fn () => $service->deactivate($sekretaris, $pengurus),
        fn () => $service->resetPassword($sekretaris, $campuran),
        fn () => $service->resetPassword($sekretaris, superAdmin()),
    ] as $attempt) {
        expect($attempt)->toThrow(AuthorizationException::class);
    }

    expect($pengurus->fresh()->name)->toBe('Pengurus Medpub')->and($pengurus->fresh()->is_active)->toBeTrue();
});

it('Sekretaris tidak bisa membuat/mengubah pengurus BEM di menu User', function (): void {
    actingAs(member(RoleName::Sekretaris))->get('/panel-bem/users')->assertForbidden();
    actingAs(member(RoleName::Sekretaris))->get('/panel-bem/users/create')->assertForbidden();
});

it('reset password & nonaktifkan akun pengaju oleh Sekretaris', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan, ['must_change_password' => false]);
    actingAs(member(RoleName::Sekretaris));

    livewire(ListApplicantAccounts::class)->callAction(TestAction::make('resetPassword')->table($pengaju));
    expect($pengaju->fresh()->must_change_password)->toBeTrue();

    livewire(ListApplicantAccounts::class)->callAction(TestAction::make('deactivate')->table($pengaju));
    expect($pengaju->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_reset', 'subject_id' => $pengaju->id]);
});

it('pindah organisasi akun pengaju tercatat; edit tidak bisa mengubah role', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan);
    $baru = Organization::factory()->create();
    actingAs(member(RoleName::Sekretaris));

    livewire(EditApplicantAccount::class, ['record' => $pengaju->getRouteKey()])
        ->fillForm(['organization_id' => $baru->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pengaju->fresh()->organization_id)->toBe($baru->id)
        ->and($pengaju->fresh()->getRoleNames()->all())->toBe(['pengaju_kegiatan']);
    $this->assertDatabaseHas('activity_logs', ['action' => 'applicant.organization_changed', 'subject_id' => $pengaju->id]);
});

it('menu Organisasi & Akun Pengaju hanya untuk Super Admin dan Sekretaris', function (): void {
    foreach (RoleName::cases() as $role) {
        $ok = in_array($role, [RoleName::SuperAdmin, RoleName::Sekretaris], true);
        $user = member($role);

        actingAs($user)->get('/panel-bem/organisasi')->assertStatus($ok ? 200 : 403);
        actingAs($user)->get('/panel-bem/akun-pengaju')->assertStatus($ok ? 200 : 403);
    }
});

it('menu User Super Admin tidak menampilkan akun pengaju', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan);
    actingAs(superAdmin());

    livewire(ListUsers::class)->assertCanNotSeeTableRecords([$pengaju]);
});
