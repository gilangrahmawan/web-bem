<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Filament\Pages\Calendar;
use App\Models\Event;
use App\Services\EventService;

use function Pest\Laravel\actingAs;

function createEvent(EventCategory $category, string $start, ?string $end = null, array $extra = []): Event
{
    return app(EventService::class)->create(superAdmin(), [
        'title' => ucfirst($category->value).' '.$start,
        'category' => $category->value,
        'all_day' => ! str_contains($start, ' '),
        'starts_at' => $start,
        'ends_at' => $end,
        ...$extra,
    ]);
}

it('agenda UKM/BEM di tanggal yang sama ditandai bentrok walau jamnya berbeda', function (): void {
    $morning = createEvent(EventCategory::Ukm, '2026-12-10 08:00', '2026-12-10 10:00');
    $evening = createEvent(EventCategory::Bem, '2026-12-10 19:00', '2026-12-10 21:00');

    expect($morning->fresh()->has_conflict)->toBeTrue()
        ->and($evening->fresh()->has_conflict)->toBeTrue();
});

it('agenda beberapa hari yang beririsan terdeteksi bentrok', function (): void {
    $long = createEvent(EventCategory::Ukm, '2026-12-01', '2026-12-05');
    $inside = createEvent(EventCategory::Ukm, '2026-12-04');
    $after = createEvent(EventCategory::Ukm, '2026-12-06');

    expect($long->fresh()->has_conflict)->toBeTrue()
        ->and($inside->fresh()->has_conflict)->toBeTrue()
        ->and($after->fresh()->has_conflict)->toBeFalse();
});

it('rapat, hari besar, dan agenda kampus tidak memicu bentrok', function (): void {
    $ukm = createEvent(EventCategory::Ukm, '2026-12-10');
    createEvent(EventCategory::Meeting, '2026-12-10');
    createEvent(EventCategory::Holiday, '2026-12-10');
    $campus = createEvent(EventCategory::Campus, '2026-12-10');

    expect($ukm->fresh()->has_conflict)->toBeFalse()
        ->and($campus->fresh()->has_conflict)->toBeFalse();
});

it('tanda bentrok diperbarui saat agenda dipindah atau dihapus', function (): void {
    $a = createEvent(EventCategory::Ukm, '2026-12-10');
    $b = createEvent(EventCategory::Ukm, '2026-12-10');
    $c = createEvent(EventCategory::Bem, '2026-12-20');

    // Pindahkan B ke tanggal C: A tidak bentrok lagi, B & C kini bentrok.
    app(EventService::class)->update(superAdmin(), $b, [
        'title' => $b->title, 'category' => 'ukm', 'all_day' => true, 'starts_at' => '2026-12-20',
    ]);

    expect($a->fresh()->has_conflict)->toBeFalse()
        ->and($b->fresh()->has_conflict)->toBeTrue()
        ->and($c->fresh()->has_conflict)->toBeTrue();

    app(EventService::class)->delete(superAdmin(), $b->fresh());

    expect($c->fresh()->has_conflict)->toBeFalse();
});

it('mengubah kategori menjadi rapat menghapus tanda bentrok', function (): void {
    $a = createEvent(EventCategory::Ukm, '2026-12-10');
    $b = createEvent(EventCategory::Ukm, '2026-12-10');

    app(EventService::class)->update(superAdmin(), $b, [
        'title' => $b->title, 'category' => 'meeting', 'all_day' => true, 'starts_at' => '2026-12-10',
    ]);

    expect($a->fresh()->has_conflict)->toBeFalse()
        ->and($b->fresh()->has_conflict)->toBeFalse();
});

it('dashboard menampilkan peringatan bentrok dan baru menyimpan setelah "Tetap simpan"', function (): void {
    createEvent(EventCategory::Ukm, '2026-12-10', null, ['title' => 'Pentas Seni UKM']);
    actingAs(member(RoleName::KoordinatorHumas));

    $page = livewire(Calendar::class)
        ->callAction('createEvent', data: [
            'title' => 'Bazar UKM Kewirausahaan',
            'category' => 'ukm',
            'all_day' => true,
            'starts_at' => '2026-12-10 00:00:00',
        ])
        ->assertActionMounted('confirmConflict');

    // Data ditahan di properti terkunci; daftar bentrok berisi agenda yang sudah ada.
    $instance = $page->instance();
    expect($instance->pendingEvent['title'] ?? null)->toBe('Bazar UKM Kewirausahaan')
        ->and((fn () => $this->pendingConflicts())->call($instance)->pluck('title')->all())->toBe(['Pentas Seni UKM'])
        ->and(Event::where('title', 'Bazar UKM Kewirausahaan')->exists())->toBeFalse();

    $page->callMountedAction()->assertHasNoFormErrors();

    expect(Event::where('title', 'Bazar UKM Kewirausahaan')->first()?->has_conflict)->toBeTrue();
});

it('dashboard menyimpan langsung bila tidak ada bentrok', function (): void {
    actingAs(member(RoleName::Sekretaris));

    livewire(Calendar::class)
        ->callAction('createEvent', data: [
            'title' => 'PKKMB 2026',
            'category' => 'bem',
            'all_day' => true,
            'starts_at' => '2026-12-15 00:00:00',
        ])
        ->assertActionNotMounted('confirmConflict')
        ->assertHasNoFormErrors();

    expect(Event::where('title', 'PKKMB 2026')->value('category'))->toBe(EventCategory::Bem);
});
