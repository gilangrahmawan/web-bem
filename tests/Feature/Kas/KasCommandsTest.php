<?php

use App\Enums\RoleName;
use App\Models\Due;
use App\Notifications\KasArrearsReminderNotification;
use App\Notifications\KasMonthlyRecapNotification;
use Illuminate\Console\Scheduling\Schedule;

use function Pest\Laravel\artisan;

it('pengingat tunggakan hanya untuk yang menunggak kas/iuran dan tidak dobel di hari yang sama', function (): void {
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    $lunas = member(RoleName::AnggotaKajian);
    $menunggak = member(RoleName::AnggotaHumas);
    $pembina = member(RoleName::Pembina);
    $lunasKasTapiIuran = member(RoleName::AnggotaMedpub);

    foreach ([$bendahara, $lunas, $lunasKasTapiIuran] as $user) {
        kas()->recordWeeks($bendahara, $user, [1, 2, 3, 4]);
    }

    $due = Due::factory()->create(['applies_to_all' => false]);
    $due->members()->sync([$lunasKasTapiIuran->id]);

    artisan('kas:remind-arrears')->assertSuccessful();
    artisan('kas:remind-arrears')->assertSuccessful();

    $count = fn ($user) => $user->notifications()->where('type', KasArrearsReminderNotification::class)->count();

    expect($count($menunggak))->toBe(1)
        ->and($count($lunasKasTapiIuran))->toBe(1)
        ->and($count($lunas))->toBe(0)
        ->and($count($bendahara))->toBe(0)
        ->and($count($pembina))->toBe(0);
});

it('pengingat tunggakan dilewati selama tanggal mulai kas belum diatur', function (): void {
    kasSetup(startDate: null);
    $anggota = member(RoleName::AnggotaHumas);

    artisan('kas:remind-arrears')->assertSuccessful()->expectsOutputToContain('Tanggal mulai kas belum diatur');

    expect($anggota->notifications()->count())->toBe(0);
});

it('rekap bulanan dikirim ke Bendahara saja', function (): void {
    kasSetup();
    $bendahara = member(RoleName::Bendahara);
    kas()->recordWeeks($bendahara, member(RoleName::AnggotaKajian), [1, 2]);
    $ketua = superAdmin();

    $this->travelTo('2026-12-01 08:00:00');
    artisan('kas:monthly-recap')->assertSuccessful();

    $notification = $bendahara->notifications()->where('type', KasMonthlyRecapNotification::class)->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Rekap kas November 2026')
        ->and($notification->data['body'])->toContain('Rp10.000')
        ->and($ketua->notifications()->where('type', KasMonthlyRecapNotification::class)->count())->toBe(0);
});

it('jadwal pengingat Senin 08:00 dan rekap tanggal 1 terdaftar', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($e) => [str($e->command)->afterLast(' ')->toString() => $e->expression]);

    expect($events['kas:remind-arrears'])->toBe('0 8 * * 1')
        ->and($events['kas:monthly-recap'])->toBe('0 8 1 * *');
});
