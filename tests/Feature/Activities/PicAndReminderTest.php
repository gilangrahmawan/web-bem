<?php

use App\Enums\ActivityStatus;
use App\Enums\RoleName;
use App\Filament\Widgets\ActivityQueueWidget;
use App\Filament\Widgets\ActivityStatusChart;
use App\Filament\Widgets\LpjDeadlineWidget;
use App\Filament\Widgets\MyActivitiesChart;
use App\Filament\Widgets\OverdueLpjWidget;
use App\Models\ActivityPicTransfer;
use App\Models\Setting;
use App\Models\StudentActivity;
use App\Notifications\ActivityNotification;
use App\Services\ActivityWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function approvedOn(string $endsOn, ?int $picId = null): StudentActivity
{
    return StudentActivity::factory()->status(ActivityStatus::Approved)->create([
        'pic_user_id' => $picId ?? member(RoleName::PengajuKegiatan)->id,
        'starts_on' => $endsOn,
        'ends_on' => $endsOn,
    ]);
}

it('pindah penanggung jawab: hanya ke akun aktif organisasi yang sama, disetujui Sekretaris, kedua pihak diberi tahu', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $lama = member(RoleName::PengajuKegiatan);
    $baru = member(RoleName::PengajuKegiatan, ['organization_id' => $lama->organization_id]);
    $lain = member(RoleName::PengajuKegiatan);
    $activity = StudentActivity::factory()->create(['pic_user_id' => $lama->id, 'organization_id' => $lama->organization_id]);
    $service = app(ActivityWorkflowService::class);

    expect(fn () => $service->requestPicTransfer($lama, $activity, $lain->id))->toThrow(ValidationException::class, 'organisasi yang sama')
        ->and(fn () => $service->requestPicTransfer($baru, $activity, $baru->id))->toThrow(AuthorizationException::class);

    $transfer = $service->requestPicTransfer($lama, $activity, $baru->id, 'Saya lulus.');
    expect($activity->fresh()->pic_user_id)->toBe($lama->id); // belum pindah sebelum disetujui

    expect(fn () => $service->decidePicTransfer($lama, $transfer, true))->toThrow(AuthorizationException::class);

    $service->decidePicTransfer($sekretaris, $transfer, true);

    expect($activity->fresh()->pic_user_id)->toBe($baru->id)
        ->and($transfer->fresh()->status)->toBe(ActivityPicTransfer::APPROVED)
        ->and($lama->notifications()->where('type', ActivityNotification::class)->count())->toBe(1)
        ->and($baru->notifications()->where('type', ActivityNotification::class)->count())->toBe(1)
        ->and($activity->logs()->where('stage', 'pic')->count())->toBe(2);
    $this->assertDatabaseHas('activity_logs', ['action' => 'activity.pic_transferred', 'subject_id' => $activity->id]);
});

it('Sekretaris boleh mengajukan pindah penanggung jawab', function (): void {
    $sekretaris = member(RoleName::Sekretaris);
    $lama = member(RoleName::PengajuKegiatan);
    $baru = member(RoleName::PengajuKegiatan, ['organization_id' => $lama->organization_id]);
    $activity = StudentActivity::factory()->create(['pic_user_id' => $lama->id, 'organization_id' => $lama->organization_id]);

    $transfer = app(ActivityWorkflowService::class)->requestPicTransfer($sekretaris, $activity, $baru->id);
    app(ActivityWorkflowService::class)->decidePicTransfer($sekretaris, $transfer, true);

    expect($activity->fresh()->pic_user_id)->toBe($baru->id);
});

it('tenggat LPJ = tanggal selesai + lpj_deadline_days (setting)', function (): void {
    $activity = approvedOn('2026-12-01');

    expect($activity->lpjDueOn()->toDateString())->toBe('2026-12-15');

    Setting::set('lpj_deadline_days', 7);
    expect($activity->fresh()->lpjDueOn()->toDateString())->toBe('2026-12-08');
});

it('pengingat LPJ: H-3, hari tenggat, lalu mingguan saat terlambat — tidak dobel di hari yang sama', function (): void {
    $activity = approvedOn('2026-12-01'); // tenggat 15 Des
    $count = fn () => $activity->pic->notifications()->where('type', ActivityNotification::class)->count();

    foreach (['2026-12-11' => 0, '2026-12-12' => 1, '2026-12-15' => 2, '2026-12-16' => 2, '2026-12-22' => 3, '2026-12-25' => 3, '2026-12-29' => 4] as $day => $expected) {
        $this->travelTo("{$day} 08:00:00");
        artisan('activities:remind-lpj')->assertSuccessful();
        artisan('activities:remind-lpj')->assertSuccessful(); // dijalankan dua kali di hari yang sama
        expect($count())->toBe($expected, "hari {$day}");
    }
});

it('LPJ terlambat ditandai dan tampil di dashboard BEM', function (): void {
    $this->travelTo('2026-12-20 09:00:00');
    $late = approvedOn('2026-12-01');
    $onTime = approvedOn('2026-12-15');

    expect($late->isLpjOverdue())->toBeTrue()->and($onTime->isLpjOverdue())->toBeFalse()
        ->and(StudentActivity::query()->lpjOverdue()->pluck('id')->all())->toBe([$late->id]);

    actingAs(member(RoleName::Sekretaris));
    livewire(OverdueLpjWidget::class)->assertCanSeeTableRecords([$late])->assertCanNotSeeTableRecords([$onTime]);
});

it('widget kegiatan mengikuti hak akses', function (): void {
    foreach (RoleName::cases() as $role) {
        actingAs(member($role));
        $bem = in_array($role, [RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::Bendahara, RoleName::Pembina], true);

        expect(ActivityStatusChart::canView())->toBe($bem, $role->value)
            ->and(ActivityQueueWidget::canView())->toBe($bem, $role->value)
            ->and(OverdueLpjWidget::canView())->toBe($bem, $role->value)
            ->and(MyActivitiesChart::canView())->toBe($role === RoleName::PengajuKegiatan, $role->value)
            ->and(LpjDeadlineWidget::canView())->toBe($role === RoleName::PengajuKegiatan, $role->value);
    }
});

it('widget pengaju hanya menghitung kegiatan organisasinya; tampilan kosong rapi', function (): void {
    $pengaju = member(RoleName::PengajuKegiatan);
    actingAs($pengaju);
    expect(livewire(MyActivitiesChart::class)->instance()->isEmpty())->toBeTrue();

    StudentActivity::factory()->count(2)->create(['organization_id' => $pengaju->organization_id, 'pic_user_id' => $pengaju->id]);
    StudentActivity::factory()->create(); // organisasi lain

    $data = (fn () => $this->getData())->call(livewire(MyActivitiesChart::class)->instance());
    expect(array_sum($data['datasets'][0]['data']))->toBe(2);

    actingAs(member(RoleName::Bendahara));
    expect(livewire(ActivityStatusChart::class)->instance()->isEmpty())->toBeFalse();
});
