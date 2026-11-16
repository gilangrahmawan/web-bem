<?php

use App\Enums\RoleName;
use App\Filament\Pages\KasWeekly;
use App\Filament\Widgets\DuesProgressChart;
use App\Filament\Widgets\KasArrearsByDivisionChart;
use App\Filament\Widgets\KasStatsOverview;
use App\Filament\Widgets\KasStatusChart;
use App\Filament\Widgets\KasWeeklyChart;
use App\Filament\Widgets\MyKasWidget;
use App\Models\KasWeeklyPayment;
use App\Support\Kas\KasExport;

use function Pest\Laravel\actingAs;

const KAS_OVERVIEW_WIDGETS = [
    KasStatsOverview::class, KasWeeklyChart::class, KasStatusChart::class,
    KasArrearsByDivisionChart::class, DuesProgressChart::class,
];

it('halaman Kas Mingguan & Iuran hanya untuk bendahara, super_admin, dan pembina (lihat)', function (): void {
    kasSetup();
    $allowed = [RoleName::SuperAdmin, RoleName::Bendahara, RoleName::Pembina];

    foreach (RoleName::cases() as $role) {
        $user = member($role);
        $expected = in_array($role, $allowed, true);

        actingAs($user)->get('/panel-bem/kas-mingguan')->assertStatus($expected ? 200 : 403);
        actingAs($user)->get('/panel-bem/iuran-tambahan')->assertStatus($expected ? 200 : 403);
    }

    actingAs(member(RoleName::Pembina))->get('/panel-bem/iuran-tambahan/create')->assertForbidden();
    actingAs(member(RoleName::Bendahara))->get('/panel-bem/iuran-tambahan/create')->assertOk();
});

it('pembina melihat grid tanpa bisa mengklik sel, dan toggle dari browser tetap ditolak', function (): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaKajian, ['name' => 'Anggota Uji']);
    actingAs(member(RoleName::Pembina));

    livewire(KasWeekly::class)
        ->assertSee('Anggota Uji')
        ->assertDontSee('+ Catat minggu')
        ->assertActionHidden('kasSettings')
        ->call('toggle', $anggota->id, 1);

    expect(KasWeeklyPayment::count())->toBe(0);
});

it('bendahara mencatat & membatalkan lewat klik sel grid', function (): void {
    kasSetup();
    $anggota = member(RoleName::AnggotaKajian);
    actingAs(member(RoleName::Bendahara));

    livewire(KasWeekly::class)->call('toggle', $anggota->id, 2);
    expect(KasWeeklyPayment::where('user_id', $anggota->id)->pluck('week_number')->all())->toBe([2]);

    livewire(KasWeekly::class)->call('toggle', $anggota->id, 2);
    expect(KasWeeklyPayment::where('user_id', $anggota->id)->count())->toBe(0);
});

it('widget ringkasan kas hanya untuk kas.view_all; Kas Saya untuk pengurus wajib kas', function (): void {
    kasSetup();

    foreach (RoleName::cases() as $role) {
        actingAs(member($role));
        $viewsAll = in_array($role, [RoleName::SuperAdmin, RoleName::Bendahara, RoleName::Pembina], true);

        foreach (KAS_OVERVIEW_WIDGETS as $widget) {
            expect($widget::canView())->toBe($viewsAll, "{$role->value} → {$widget}");
        }

        expect(MyKasWidget::canView())->toBe(! in_array($role, [RoleName::Pembina, RoleName::PengajuKegiatan], true), "{$role->value} → Kas Saya");
    }
});

it('dashboard anggota hanya memuat Kas Saya miliknya, bukan data kas orang lain', function (): void {
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    $saya = member(RoleName::AnggotaHumas);
    $lain = member(RoleName::AnggotaHumas, ['name' => 'Pengurus Lain Menunggak']);
    kas()->recordWeeks($bendahara, $saya, [1, 2, 3, 4]);

    // Widget dashboard dimuat lazy → dashboard dicek terbuka, lalu widget dirender langsung.
    actingAs($saya)->get('/panel-bem')->assertOk();

    livewire(MyKasWidget::class)
        ->assertSee('Kas Saya')
        ->assertSee('4/4')
        ->assertSee('Lunas')
        ->assertDontSee('Pengurus Lain Menunggak');

    expect(KasStatsOverview::canView())->toBeFalse();

    // Kas Saya selalu milik user login: pengurus lain melihat tunggakannya sendiri.
    actingAs($lain);
    livewire(MyKasWidget::class)->assertSee('0/4')->assertSee('Rp20.000');

    actingAs(member(RoleName::Pembina))->get('/panel-bem')->assertOk();
    livewire(KasStatsOverview::class)->assertSee('Ringkasan kas mingguan')->assertSee('Rp20.000');
});

it('grafik menampilkan tampilan kosong yang rapi bila belum ada data', function (): void {
    kasSetup(startDate: null);
    actingAs(member(RoleName::Bendahara));

    foreach ([KasWeeklyChart::class, KasStatusChart::class, KasArrearsByDivisionChart::class, DuesProgressChart::class] as $widget) {
        expect(livewire($widget)->instance()->isEmpty())->toBeTrue($widget);
    }

    livewire(KasWeeklyChart::class)->assertSee('Belum ada data kas')->assertSee('Tanggal mulai kas belum diatur');
    livewire(DuesProgressChart::class)->assertSee('Belum ada iuran tambahan');
});

it('grafik berisi data kas setelah ada pembayaran', function (): void {
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    $a = member(RoleName::AnggotaKajian);
    member(RoleName::AnggotaHumas); // menunggak
    kas()->recordWeeks($bendahara, $a, [1, 2, 3, 4]);
    actingAs($bendahara);

    $weekly = (fn () => $this->getData())->call(livewire(KasWeeklyChart::class)->instance());
    $status = (fn () => $this->getData())->call(livewire(KasStatusChart::class)->instance());

    expect($weekly['labels'])->toBe(['M1', 'M2', 'M3', 'M4'])
        ->and($weekly['datasets'][0]['data'])->toBe([5000, 5000, 5000, 5000])
        ->and($status['datasets'][0]['data'][0])->toBe(1); // satu pengurus lunas
});

it('export berisi kolom yang benar dan aman dari formula injection', function (): void {
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    $jahil = member(RoleName::AnggotaKajian, ['name' => '=HYPERLINK("http://evil.test","klik")']);
    kas()->recordWeeks($bendahara, $jahil, [1]);

    $rows = KasExport::rows(kas()->memberSummaries(), kas()->weeks(), kas()->currentWeekNumber());
    $row = collect($rows)->first(fn (array $r) => str_contains((string) $r[0], 'HYPERLINK'));

    expect($rows[0][0])->toBe('Nama')
        ->and($rows[0][4])->toBe('M1 (02/11/2026)')
        ->and($row[0])->toStartWith("'=")
        ->and($row[4])->toBe(5000)
        ->and($row[5])->toBe('BELUM')
        ->and(KasExport::sanitize('-'))->toBe('-')
        ->and(KasExport::sanitize('+62812'))->toBe("'+62812");

    actingAs($bendahara);
    livewire(KasWeekly::class)->callAction('exportCsv')->assertFileDownloaded('kas-mingguan-2026-11-25.csv');
});

it('anggota tidak bisa mengunduh export kas', function (): void {
    kasSetup();
    actingAs(member(RoleName::AnggotaKajian))->get('/panel-bem/kas-mingguan')->assertForbidden();
});
