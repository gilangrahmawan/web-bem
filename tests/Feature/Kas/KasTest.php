<?php

use App\Enums\RoleName;
use App\Filament\Widgets\KasStatsOverview;
use App\Filament\Widgets\MyKasWidget;
use App\Models\KasWeeklyPayment;
use App\Models\User;
use App\Notifications\KasPaidNotification;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

// --- Tanggal mulai kas ---------------------------------------------------------------------

it('selama kas_start_date kosong: tidak ada minggu, tidak ada tunggakan, dan pencatatan ditolak', function (): void {
    kasSetup(startDate: null);
    $anggota = member(RoleName::AnggotaKajian);
    $bendahara = member(RoleName::Bendahara);

    expect(kas()->weeks())->toBe([])
        ->and(kas()->currentWeekNumber())->toBe(0)
        ->and(kas()->setupProblem())->toBe('Tanggal mulai kas belum diatur.')
        ->and(kas()->summaryFor($anggota)->arrears)->toBe(0)
        ->and(kas()->summaryFor($anggota)->firstWeek)->toBeNull()
        ->and(kas()->totals())->toMatchArray(['arrears' => 0, 'collected' => 0]);

    expect(fn () => kas()->recordWeeks($bendahara, $anggota, [1]))->toThrow(ValidationException::class);
    expect(KasWeeklyPayment::count())->toBe(0);
});

it('menampilkan pesan "Tanggal mulai kas belum diatur" di halaman Kas dan widget', function (): void {
    kasSetup(startDate: null);

    $this->actingAs(member(RoleName::Bendahara))
        ->get('/panel-bem/kas-mingguan')->assertOk()->assertSee('Tanggal mulai kas belum diatur');

    // Widget dashboard dimuat lazy, jadi dirender langsung sebagai komponen.
    $this->actingAs(member(RoleName::AnggotaHumas));
    livewire(MyKasWidget::class)->assertSee('Kas Saya')->assertSee('Tanggal mulai kas belum diatur');

    $this->actingAs(member(RoleName::Bendahara));
    livewire(KasStatsOverview::class)->assertSee('Tanggal mulai kas belum diatur');
});

it('minggu ke-1 = Senin pertama pada atau setelah kas_start_date', function (string $start, string $expectedFirstMonday): void {
    kasSetup(startDate: $start);

    expect(kas()->weeks()[0]->start->toDateString())->toBe($expectedFirstMonday)
        ->and(kas()->weeks()[1]->start->toDateString())->toBe(CarbonImmutable::parse($expectedFirstMonday)->addWeek()->toDateString());
})->with([
    'Sabtu → Senin berikutnya' => ['2026-08-01', '2026-08-03'],
    'Senin → hari itu juga' => ['2026-11-02', '2026-11-02'],
    'Minggu → besoknya' => ['2026-11-08', '2026-11-09'],
]);

it('minggu terakhir tidak melewati akhir periode aktif', function (): void {
    kasSetup(startDate: '2027-07-01');

    $weeks = kas()->weeks();
    expect(end($weeks)->start->lessThanOrEqualTo(CarbonImmutable::parse('2027-07-31')))->toBeTrue()
        ->and(count($weeks))->toBe(4); // 5, 12, 19, 26 Juli
});

// --- kas_starts_on per pengurus ------------------------------------------------------------

it('kewajiban user = tanggal yang lebih akhir antara kas_start_date dan kas_starts_on', function (): void {
    kasSetup(); // umum mulai 2 Nov, hari ini 25 Nov (minggu ke-4)

    $lama = member(RoleName::AnggotaMedpub);
    $baru = member(RoleName::AnggotaMedpub, ['kas_starts_on' => '2026-11-12']); // Kamis → Senin 16 Nov = minggu ke-3
    $lebihAwal = member(RoleName::AnggotaMedpub, ['kas_starts_on' => '2026-09-01']); // lebih awal dari umum → ikut umum

    expect(kas()->firstWeekFor($lama))->toBe(1)
        ->and(kas()->firstWeekFor($baru))->toBe(3)
        ->and(kas()->firstWeekFor($lebihAwal))->toBe(1);

    expect(kas()->summaryFor($lama)->unpaidWeeks)->toBe([1, 2, 3, 4])
        ->and(kas()->summaryFor($baru)->unpaidWeeks)->toBe([3, 4])
        ->and(kas()->summaryFor($baru)->arrears)->toBe(10000)
        ->and(kas()->summaryFor($baru)->isRequired(2))->toBeFalse()
        ->and(kas()->summaryFor($baru)->isRequired(3))->toBeTrue();
});

it('minggu sebelum mulai wajib seorang pengurus tidak bisa dicatat', function (): void {
    kasSetup();
    $baru = member(RoleName::AnggotaHumas, ['kas_starts_on' => '2026-11-16']);

    expect(fn () => kas()->recordWeeks(member(RoleName::Bendahara), $baru, [2]))->toThrow(ValidationException::class);

    kas()->recordWeeks(member(RoleName::Bendahara), $baru, [3]);
    expect(KasWeeklyPayment::where('user_id', $baru->id)->pluck('week_number')->all())->toBe([3]);
});

it('kas_starts_on per user hanya bisa diatur pemegang kas.manage dan tercatat di log', function (): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaKajian);

    expect(fn () => kas()->setMemberStart(member(RoleName::KoordinatorKajian), $anggota, '2026-11-16'))
        ->toThrow(AuthorizationException::class);

    kas()->setMemberStart(member(RoleName::Bendahara), $anggota, '2026-11-16');

    expect($anggota->fresh()->kas_starts_on->toDateString())->toBe('2026-11-16')
        ->and(kas()->firstWeekFor($anggota->fresh()))->toBe(3);
    $this->assertDatabaseHas('activity_logs', ['action' => 'kas.member_start_updated', 'subject_id' => $anggota->id]);
});

// --- Wajib kas & tunggakan -----------------------------------------------------------------

it('wajib kas: semua pengurus aktif ber-role kecuali Pembina; nonaktif tidak ditagih', function (): void {
    kasSetup();
    $ketua = superAdmin();
    $bendahara = member(RoleName::Bendahara);
    $pembina = member(RoleName::Pembina);
    $nonaktif = member(RoleName::AnggotaKajian, ['is_active' => false]);
    $tanpaRole = User::factory()->create();

    $payers = kas()->payersQuery()->pluck('id');

    expect($payers)->toContain($ketua->id)->toContain($bendahara->id)
        ->not->toContain($pembina->id)->not->toContain($nonaktif->id)->not->toContain($tanpaRole->id)
        ->and(kas()->summaryFor($pembina))->toBeNull();
});

it('tunggakan = minggu jatuh tempo belum dibayar × nominal saat ini; bayar di muka tidak mengurangi tunggakan lama', function (): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaHumas);
    $bendahara = member(RoleName::Bendahara);

    kas()->recordWeeks($bendahara, $anggota, [1, 2]);
    kas()->recordWeeks($bendahara, $anggota, [6]); // di muka

    $summary = kas()->summaryFor($anggota);
    expect($summary->unpaidWeeks)->toBe([3, 4])
        ->and($summary->arrears)->toBe(10000)
        ->and($summary->paidTotal())->toBe(15000)
        ->and($summary->paidDueWeeksCount())->toBe(2);

    // Nominal naik: tunggakan ikut nominal baru, riwayat pembayaran tetap.
    kas()->updateSettings($bendahara, 7000, '2026-11-02');
    expect(kas()->summaryFor($anggota)->arrears)->toBe(14000)
        ->and(KasWeeklyPayment::where('user_id', $anggota->id)->pluck('amount')->unique()->all())->toBe([5000]);
});

it('pembayaran ganda untuk minggu yang sama ditolak', function (): void {
    $period = kasSetup();
    $anggota = member(RoleName::AnggotaKajian);
    $bendahara = member(RoleName::Bendahara);

    kas()->recordWeeks($bendahara, $anggota, [1]);

    expect(fn () => kas()->recordWeeks($bendahara, $anggota, [1, 2]))->toThrow(ValidationException::class)
        ->and(KasWeeklyPayment::where('user_id', $anggota->id)->count())->toBe(1);

    // Database juga menolak (unique index) bila validasi terlewati.
    expect(fn () => KasWeeklyPayment::create([
        'period_id' => $period->id, 'user_id' => $anggota->id, 'week_number' => 1,
        'week_start' => '2026-11-02', 'amount' => 5000, 'paid_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('batal bayar menghapus minggu itu dan tercatat di log aktivitas', function (): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaKajian);
    $bendahara = member(RoleName::Bendahara);

    kas()->recordWeeks($bendahara, $anggota, [1, 2]);
    kas()->cancelWeek($bendahara, $anggota, 2);

    expect(KasWeeklyPayment::where('user_id', $anggota->id)->pluck('week_number')->all())->toBe([1]);
    $this->assertDatabaseHas('activity_logs', ['action' => 'kas.paid', 'subject_id' => $anggota->id]);
    $this->assertDatabaseHas('activity_logs', ['action' => 'kas.cancelled', 'subject_id' => $anggota->id]);
});

it('hanya pemegang kas.manage yang bisa mencatat; pembina & koordinator ditolak', function (RoleName $role): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaKajian);

    expect(fn () => kas()->recordWeeks(member($role), $anggota, [1]))->toThrow(AuthorizationException::class)
        ->and(fn () => kas()->updateSettings(member($role), 1000, null))->toThrow(AuthorizationException::class);
})->with([RoleName::Pembina, RoleName::KoordinatorKajian, RoleName::Sekretaris, RoleName::AnggotaMedpub]);

it('catat beberapa minggu sekaligus mengirim satu notifikasi berisi rentang minggu', function (): void {
    Notification::fake();
    kasSetup();
    $anggota = member(RoleName::AnggotaMedpub, ['name' => 'Rina Kartika']);

    kas()->recordWeeks(member(RoleName::Bendahara), $anggota, kas()->nextUnpaidWeeks($anggota, 4));

    Notification::assertSentToTimes($anggota, KasPaidNotification::class, 1);
    Notification::assertSentTo($anggota, KasPaidNotification::class, function (KasPaidNotification $n) use ($anggota): bool {
        return $n->message($anggota) === 'Terima kasih, Rina! Pembayaran kas minggu ke-1–4 sebesar Rp20.000 sudah kami terima.';
    });
});

it('teks notifikasi satu minggu sesuai AGENTS.md', function (): void {
    $user = new User(['name' => 'Budi Santoso']);

    expect((new KasPaidNotification([3], 5000))->message($user))
        ->toBe('Terima kasih, Budi! Pembayaran kas minggu ke-3 sebesar Rp5.000 sudah kami terima.')
        ->and(KasPaidNotification::weekList([7, 3, 5, 6]))->toBe('3, 5–7');
});
