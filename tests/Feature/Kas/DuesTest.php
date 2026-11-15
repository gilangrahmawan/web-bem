<?php

use App\Enums\DueType;
use App\Enums\RoleName;
use App\Filament\Resources\Dues\Pages\ListDues;
use App\Models\Due;
use App\Models\DuePayment;
use App\Notifications\DuePaidNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

it('iuran bulanan ditagih per bulan sejak tenggat pertama, target sampai akhir periode', function (): void {
    kasSetup(); // hari ini 25 Nov 2026, periode berakhir 31 Jul 2027
    $anggota = member(RoleName::AnggotaKajian);
    $due = Due::factory()->monthly()->create(['amount' => 50000, 'due_date' => '2026-10-15']);

    // Tagihan: 15 Okt, 15 Nov → 2 bulan. Target: Okt 2026 – Jul 2027 → 10 bulan.
    expect(kas()->dueStatus($due, $anggota))->toBe([
        'target' => 500000, 'billed' => 100000, 'paid' => 0, 'arrears' => 100000, 'remaining' => 500000,
    ]);
});

it('iuran sekali bayar ditagih penuh sejak dibuat', function (): void {
    kasSetup();
    $due = Due::factory()->create(['amount' => 150000, 'type' => DueType::Once, 'due_date' => '2027-01-31']);

    expect(kas()->dueStatus($due, member(RoleName::AnggotaHumas)))
        ->toMatchArray(['target' => 150000, 'billed' => 150000, 'arrears' => 150000]);
});

it('pembayaran iuran boleh dicicil, tidak boleh melebihi sisa, dan mengirim notifikasi', function (): void {
    Notification::fake();
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    $anggota = member(RoleName::AnggotaMedpub);
    $due = Due::factory()->create(['amount' => 150000]);

    kas()->recordDuePayment($bendahara, $due, $anggota, 50000, 'Cicilan 1');
    kas()->recordDuePayment($bendahara, $due, $anggota, 60000);

    expect(kas()->dueStatus($due, $anggota))->toMatchArray(['paid' => 110000, 'remaining' => 40000]);
    expect(fn () => kas()->recordDuePayment($bendahara, $due, $anggota, 40001))->toThrow(ValidationException::class);
    expect(fn () => kas()->recordDuePayment($bendahara, $due, $anggota, 0))->toThrow(ValidationException::class);

    kas()->recordDuePayment($bendahara, $due, $anggota, 40000);
    expect(kas()->dueStatus($due, $anggota)['remaining'])->toBe(0)
        ->and(kas()->outstandingDuesFor($anggota))->toBe([]);

    Notification::assertSentToTimes($anggota, DuePaidNotification::class, 3);
    $this->assertDatabaseHas('activity_logs', ['action' => 'due.paid']);
});

it('hanya peserta yang ditagih; pembayaran dari non-peserta ditolak', function (): void {
    kasSetup();
    $peserta = member(RoleName::AnggotaKajian);
    $bukan = member(RoleName::AnggotaHumas);
    $due = Due::factory()->create(['applies_to_all' => false]);
    $due->members()->sync([$peserta->id]);

    expect(kas()->dueParticipants($due)->pluck('id')->all())->toBe([$peserta->id])
        ->and(kas()->outstandingDuesFor($bukan))->toBe([])
        ->and(kas()->outstandingDuesFor($peserta))->toHaveCount(1);

    expect(fn () => kas()->recordDuePayment(member(RoleName::Bendahara), $due, $bukan, 10000))->toThrow(ValidationException::class);
});

it('iuran untuk semua tidak menagih pembina', function (): void {
    kasSetup();
    $due = Due::factory()->create(['applies_to_all' => true]);
    $pembina = member(RoleName::Pembina);

    expect(kas()->dueParticipants($due)->pluck('id'))->not->toContain($pembina->id);
});

it('hanya due.manage yang mencatat pembayaran; iuran berpembayaran tidak bisa dihapus', function (): void {
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    $due = Due::factory()->create();

    expect(fn () => kas()->recordDuePayment(member(RoleName::Pembina), $due, member(RoleName::AnggotaKajian), 1000))
        ->toThrow(AuthorizationException::class)
        ->and(Gate::forUser($bendahara)->allows('delete', $due))->toBeTrue();

    kas()->recordDuePayment($bendahara, $due, member(RoleName::AnggotaKajian), 1000);

    expect(Gate::forUser($bendahara)->allows('delete', $due->fresh()))->toBeFalse()
        ->and(Gate::forUser(member(RoleName::Pembina))->allows('update', $due))->toBeFalse()
        ->and(Gate::forUser(member(RoleName::Pembina))->allows('viewAny', Due::class))->toBeTrue();
});

it('bendahara mencatat pembayaran iuran dari daftar iuran', function (): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaKajian);
    $due = Due::factory()->create(['amount' => 100000]);
    actingAs(member(RoleName::Bendahara));

    livewire(ListDues::class)
        ->callAction(TestAction::make('recordPayment')->table($due), data: [
            'user_id' => $anggota->id,
            'amount' => 25000,
        ])
        ->assertHasNoFormErrors();

    expect(DuePayment::where('due_id', $due->id)->where('user_id', $anggota->id)->sum('amount'))->toEqual(25000);
});
