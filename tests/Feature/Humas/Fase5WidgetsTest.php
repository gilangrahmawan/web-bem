<?php

use App\Enums\AnnouncementType;
use App\Enums\RoleName;
use App\Filament\Widgets\AspirationStatusChart;
use App\Filament\Widgets\AspirationWeeklyChart;
use App\Filament\Widgets\DiscussionStatusChart;
use App\Filament\Widgets\InboxStatsWidget;
use App\Filament\Widgets\MessageReadRateChart;
use App\Models\Aspiration;
use App\Services\AnnouncementService;
use App\Services\AspirationService;

use function Pest\Laravel\actingAs;

it('widget Fase 5 tampil sesuai hak akses', function (): void {
    $expect = [
        AspirationStatusChart::class => [RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::KoordinatorKajian, RoleName::AnggotaKajian, RoleName::KoordinatorHumas, RoleName::AnggotaHumas, RoleName::Pembina],
        MessageReadRateChart::class => [RoleName::SuperAdmin, RoleName::Sekretaris, RoleName::KoordinatorHumas, RoleName::AnggotaHumas, RoleName::Pembina],
        DiscussionStatusChart::class => [RoleName::SuperAdmin, RoleName::KoordinatorKajian, RoleName::AnggotaKajian, RoleName::Pembina],
    ];

    foreach (RoleName::cases() as $role) {
        actingAs(member($role));

        foreach ($expect as $widget => $roles) {
            expect($widget::canView())->toBe(in_array($role, $roles, true), "{$role->value} → {$widget}");
        }

        expect(InboxStatsWidget::canView())->toBeTrue()
            ->and(AspirationWeeklyChart::canView())->toBe(AspirationStatusChart::canView());
    }
});

it('grafik aspirasi Kajian hanya menghitung aspirasi yang diteruskan ke Kajian', function (): void {
    Aspiration::factory()->count(3)->create();
    $dikaji = Aspiration::factory()->create();
    app(AspirationService::class)->review(member(RoleName::KoordinatorHumas), $dikaji);
    app(AspirationService::class)->escalate(member(RoleName::KoordinatorHumas), $dikaji, 'x');
    app(AspirationService::class)->forwardToKajian(superAdmin(), $dikaji);

    actingAs(member(RoleName::AnggotaKajian));
    $data = (fn () => $this->getData())->call(livewire(AspirationStatusChart::class)->instance());
    expect(array_sum($data['datasets'][0]['data']))->toBe(1);

    actingAs(member(RoleName::AnggotaHumas));
    $data = (fn () => $this->getData())->call(livewire(AspirationStatusChart::class)->instance());
    expect(array_sum($data['datasets'][0]['data']))->toBe(4);
});

it('grafik tampil kosong dengan rapi bila belum ada data', function (): void {
    actingAs(superAdmin());

    foreach ([AspirationStatusChart::class, AspirationWeeklyChart::class, DiscussionStatusChart::class, MessageReadRateChart::class] as $widget) {
        expect(livewire($widget)->instance()->isEmpty())->toBeTrue($widget);
    }

    livewire(AspirationStatusChart::class)->assertSee('Belum ada aspirasi');
});

it('Kotak Masuk saya menghitung pesan milik user login saja', function (): void {
    $saya = member(RoleName::AnggotaMedpub);
    $lain = member(RoleName::AnggotaHumas);
    $service = app(AnnouncementService::class);
    $service->send(member(RoleName::Sekretaris), AnnouncementType::Announcement, ['title' => 'A', 'body' => 'b', 'users' => [$saya->id, $lain->id]]);
    $service->send(member(RoleName::KoordinatorHumas), AnnouncementType::Letter, ['title' => 'S', 'body' => 'b', 'users' => [$saya->id]]);

    actingAs($saya);
    $stats = collect((fn () => $this->getStats())->call(livewire(InboxStatsWidget::class)->instance()))
        ->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getValue()]);

    expect($stats['Pengumuman belum dibaca'])->toBe(1)
        ->and($stats['Surat belum dibaca'])->toBe(1)
        ->and($stats['Surat perlu ditindaklanjuti'])->toBe(1);
});

it('tingkat baca: pembuat hanya melihat kirimannya sendiri', function (): void {
    member(RoleName::Bendahara);
    $sekretaris = member(RoleName::Sekretaris);
    $service = app(AnnouncementService::class);
    $service->send($sekretaris, AnnouncementType::Announcement, ['title' => 'Milik Sekretaris', 'body' => 'b', 'target_all' => true]);
    $service->send(member(RoleName::KoordinatorHumas), AnnouncementType::Letter, ['title' => 'Milik Humas', 'body' => 'b', 'target_all' => true]);

    actingAs($sekretaris);
    $data = (fn () => $this->getData())->call(livewire(MessageReadRateChart::class)->instance());
    expect($data['labels'])->toBe(['Milik Sekretaris']);

    actingAs(member(RoleName::Pembina));
    $data = (fn () => $this->getData())->call(livewire(MessageReadRateChart::class)->instance());
    expect($data['labels'])->toHaveCount(2);
});
