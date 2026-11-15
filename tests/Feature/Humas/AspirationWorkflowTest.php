<?php

use App\Enums\AspirationStatus;
use App\Enums\DiscussionStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Aspirations\Pages\ListAspirations;
use App\Filament\Resources\Aspirations\Pages\ViewAspiration;
use App\Models\Aspiration;
use App\Models\Discussion;
use App\Notifications\AspirationActivityNotification;
use App\Policies\AspirationPolicy;
use App\Services\AspirationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function aspirations(): AspirationService
{
    return app(AspirationService::class);
}

it('alur lengkap lewat Kajian: new → in_review → escalated → in_study → returned → resolved', function (): void {
    Notification::fake();
    $humas = member(RoleName::KoordinatorHumas);
    $anggotaHumas = member(RoleName::AnggotaHumas);
    $ketua = superAdmin();
    $koorKajian = member(RoleName::KoordinatorKajian);

    $aspiration = aspirations()->submit(['category' => 'academic', 'body' => 'Jadwal UAS bentrok antar prodi, mohon ditinjau.']);
    Notification::assertSentTo([$humas, $anggotaHumas], AspirationActivityNotification::class);
    Notification::assertNotSentTo($ketua, AspirationActivityNotification::class);

    aspirations()->review($anggotaHumas, $aspiration);
    aspirations()->escalate($humas, $aspiration, 'Perlu keputusan pimpinan.');
    Notification::assertSentTo($ketua, AspirationActivityNotification::class);

    aspirations()->forwardToKajian($ketua, $aspiration, 'Mohon dikaji dampaknya.');
    Notification::assertSentTo($koorKajian, AspirationActivityNotification::class);

    aspirations()->submitStudy($koorKajian, $aspiration, 'Bentrok terjadi karena dua prodi memakai ruang yang sama.');
    expect($aspiration->fresh()->status)->toBe(AspirationStatus::Returned);

    $discussion = Discussion::where('aspiration_id', $aspiration->id)->sole();
    expect($discussion->status)->toBe(DiscussionStatus::Discussed)
        ->and($discussion->title)->toContain($aspiration->tracking_code)
        ->and($discussion->created_by)->toBe($koorKajian->id);

    aspirations()->resolve($anggotaHumas, $aspiration, 'BEM sudah menyampaikan ke BAAK; jadwal direvisi.');

    $aspiration->refresh();
    expect($aspiration->status)->toBe(AspirationStatus::Resolved)
        ->and($aspiration->logs->pluck('to_status')->map->value->all())
        ->toBe(['new', 'in_review', 'escalated', 'in_study', 'returned', 'resolved']);

    $this->assertDatabaseHas('activity_logs', ['action' => 'aspiration.status_changed', 'subject_id' => $aspiration->id]);
});

it('menyelesaikan tanpa tanggapan untuk pengirim ditolak', function (): void {
    $humas = member(RoleName::AnggotaHumas);
    $aspiration = Aspiration::factory()->create();
    aspirations()->review($humas, $aspiration);

    expect(fn () => aspirations()->resolve($humas, $aspiration, ''))->toThrow(ValidationException::class)
        ->and($aspiration->fresh()->status)->toBe(AspirationStatus::InReview);
});

it('perpindahan status yang tidak sah ditolak', function (): void {
    $humas = member(RoleName::KoordinatorHumas);
    $aspiration = Aspiration::factory()->create(); // new

    expect(fn () => aspirations()->escalate($humas, $aspiration, 'langsung'))->toThrow(ValidationException::class)
        ->and(fn () => aspirations()->forwardToKajian(superAdmin(), $aspiration))->toThrow(ValidationException::class)
        ->and(fn () => aspirations()->submitStudy(member(RoleName::KoordinatorKajian), $aspiration, 'x'))->toThrow(ValidationException::class);
});

it('izin per tahap: anggota Humas tidak bisa eskalasi, Humas tidak bisa meneruskan ke Kajian, anggota Kajian tidak bisa mengkaji', function (): void {
    $aspiration = Aspiration::factory()->create();
    aspirations()->review(member(RoleName::AnggotaHumas), $aspiration);

    expect(fn () => aspirations()->escalate(member(RoleName::AnggotaHumas), $aspiration, 'x'))->toThrow(AuthorizationException::class);

    aspirations()->escalate(member(RoleName::KoordinatorHumas), $aspiration, 'perlu pimpinan');

    expect(fn () => aspirations()->forwardToKajian(member(RoleName::KoordinatorHumas), $aspiration))->toThrow(AuthorizationException::class);

    aspirations()->forwardToKajian(superAdmin(), $aspiration);

    expect(fn () => aspirations()->submitStudy(member(RoleName::AnggotaKajian), $aspiration, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => aspirations()->review(member(RoleName::Pembina), Aspiration::factory()->create()))->toThrow(AuthorizationException::class);
});

it('penolakan wajib alasan untuk pengirim yang memadai; eskalasi hanya bisa ditolak Ketua/Wakil', function (): void {
    $aspiration = Aspiration::factory()->create();

    expect(fn () => aspirations()->reject(member(RoleName::AnggotaHumas), $aspiration, 'tidak'))->toThrow(ValidationException::class);

    $escalated = Aspiration::factory()->create();
    aspirations()->review(member(RoleName::KoordinatorHumas), $escalated);
    aspirations()->escalate(member(RoleName::KoordinatorHumas), $escalated, 'perlu pimpinan');

    expect(fn () => aspirations()->reject(member(RoleName::AnggotaHumas), $escalated, 'Di luar kewenangan BEM, silakan ke BAA.'))
        ->toThrow(AuthorizationException::class);

    aspirations()->reject(superAdmin(), $escalated, 'Di luar kewenangan BEM, silakan sampaikan ke BAA.', 'internal');

    expect($escalated->fresh())->status->toBe(AspirationStatus::Rejected)
        ->rejection_reason->toBe('Di luar kewenangan BEM, silakan sampaikan ke BAA.');
});

it('Kajian hanya melihat aspirasi yang sudah diteruskan ke Kajian', function (): void {
    $baru = Aspiration::factory()->create();
    $dikaji = Aspiration::factory()->create();
    aspirations()->review(member(RoleName::KoordinatorHumas), $dikaji);
    aspirations()->escalate(member(RoleName::KoordinatorHumas), $dikaji, 'x');
    aspirations()->forwardToKajian(superAdmin(), $dikaji);

    foreach ([RoleName::KoordinatorKajian, RoleName::AnggotaKajian] as $role) {
        $user = member($role);
        expect(Aspiration::query()->visibleTo($user)->pluck('id')->all())->toBe([$dikaji->id])
            ->and(Gate::forUser($user)->allows('view', $baru))->toBeFalse();
    }

    foreach ([RoleName::Sekretaris, RoleName::Pembina, RoleName::AnggotaHumas] as $role) {
        expect(Aspiration::query()->visibleTo(member($role))->count())->toBe(2);
    }

    foreach ([RoleName::Bendahara, RoleName::AnggotaMedpub, RoleName::KoordinatorMedpub] as $role) {
        expect(Gate::forUser(member($role))->allows('viewAny', Aspiration::class))->toBeFalse();
    }
});

it('akses halaman aspirasi sesuai matriks D3', function (): void {
    $aspiration = Aspiration::factory()->create();
    $allowed = [RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::KoordinatorKajian, RoleName::AnggotaKajian,
        RoleName::KoordinatorHumas, RoleName::AnggotaHumas, RoleName::Pembina];

    foreach (RoleName::cases() as $role) {
        actingAs(member($role))->get('/panel-bem/aspirasi')->assertStatus(in_array($role, $allowed, true) ? 200 : 403);
    }

    actingAs(member(RoleName::AnggotaKajian))->get("/panel-bem/aspirasi/{$aspiration->id}")->assertNotFound();
    actingAs(member(RoleName::AnggotaHumas))->get("/panel-bem/aspirasi/{$aspiration->id}")->assertOk();
});

it('nama & kontak pengirim hanya terlihat oleh Humas dan Super Admin', function (): void {
    $aspiration = Aspiration::factory()->create(['name' => 'Pengirim Rahasia Sekali', 'contact' => 'rahasia@contoh.test']);

    foreach ([RoleName::SuperAdmin, RoleName::KoordinatorHumas, RoleName::AnggotaHumas] as $role) {
        expect(AspirationPolicy::seesIdentity(member($role)))->toBeTrue();
    }

    actingAs(member(RoleName::AnggotaHumas))->get("/panel-bem/aspirasi/{$aspiration->id}")
        ->assertSee('Pengirim Rahasia Sekali')->assertSee('rahasia@contoh.test');

    foreach ([RoleName::Sekretaris, RoleName::Pembina] as $role) {
        actingAs(member($role))->get("/panel-bem/aspirasi/{$aspiration->id}")->assertOk()
            ->assertDontSee('Pengirim Rahasia Sekali')->assertDontSee('rahasia@contoh.test');

        actingAs(member($role));
        livewire(ListAspirations::class, ['activeTab' => 'all'])->assertDontSee('Pengirim Rahasia Sekali');
    }
});

it('tombol aksi di halaman detail mengikuti status & izin, dan penolakan dari dashboard menyimpan alasan', function (): void {
    $aspiration = Aspiration::factory()->create();
    actingAs(member(RoleName::KoordinatorHumas));

    livewire(ViewAspiration::class, ['record' => $aspiration->getRouteKey()])
        ->assertActionVisible('review')
        ->assertActionHidden('escalate')
        ->assertActionHidden('forward')
        ->callAction('reject', data: ['rejection_reason' => 'Mohon maaf, di luar kewenangan BEM. Silakan ke BAA.', 'note' => 'spam?'])
        ->assertHasNoFormErrors();

    expect($aspiration->fresh()->status)->toBe(AspirationStatus::Rejected);

    actingAs(member(RoleName::Pembina));
    livewire(ViewAspiration::class, ['record' => Aspiration::factory()->create()->getRouteKey()])
        ->assertActionHidden('review')
        ->assertActionHidden('reject');
});

it('permission aspiration.view ditambahkan tanpa menghapus permission lain', function (): void {
    $sekretaris = Role::findByName('sekretaris', 'web');
    $sekretaris->givePermissionTo('kas.manage'); // perubahan manual lewat Shield
    $before = $sekretaris->permissions()->pluck('name')->sort()->values()->all();

    $migration = require database_path('migrations/2026_11_20_100000_add_aspiration_view_permission.php');
    $migration->up();

    $after = $sekretaris->fresh()->permissions()->pluck('name')->sort()->values()->all();

    expect($after)->toContain('aspiration.view')->toContain('kas.manage')
        ->and(array_diff($before, $after))->toBe([]);

    foreach (['anggota_kajian', 'pembina'] as $role) {
        expect(Role::findByName($role, 'web')->hasPermissionTo('aspiration.view'))->toBeTrue();
    }
});
