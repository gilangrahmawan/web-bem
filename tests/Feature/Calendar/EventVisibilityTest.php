<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Filament\Pages\Calendar;
use App\Models\Event;
use App\Models\EventAssignee;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/** Satu agenda per kategori pada tanggal yang sama. @return array<string, Event> */
function oneEventPerCategory(string $date): array
{
    $events = [];

    foreach (EventCategory::cases() as $category) {
        $events[$category->value] = Event::factory()->category($category)->on($date)->create(['title' => 'Agenda '.$category->value]);
    }

    return $events;
}

function feedRange(): array
{
    return ['start' => now()->startOfMonth()->toIso8601String(), 'end' => now()->startOfMonth()->addDays(42)->toIso8601String()];
}

it('endpoint publik hanya mengembalikan kategori publik', function (): void {
    oneEventPerCategory(now()->startOfMonth()->addDays(10)->toDateString());

    $categories = collect(getJson(route('public.calendar.feed', feedRange()))->assertOk()->json())
        ->pluck('extendedProps.category')
        ->sort()->values()->all();

    expect($categories)->toBe(['bem', 'campus', 'commemoration', 'holiday', 'ukm']);
});

it('endpoint publik menolak rentang tidak valid atau terlalu panjang', function (array $query): void {
    getJson(route('public.calendar.feed', $query))->assertUnprocessable();
})->with([
    'kosong' => [[]],
    'bukan tanggal' => [['start' => 'kemarin', 'end' => 'besok']],
    'terbalik' => [['start' => '2026-12-01', 'end' => '2026-11-01']],
    'lebih dari 100 hari' => [['start' => '2026-01-01', 'end' => '2026-12-31']],
]);

it('halaman kalender publik dan beranda menampilkan agenda publik, bukan internal', function (): void {
    $date = now()->addDays(3)->toDateString();
    Event::factory()->category(EventCategory::Bem)->on($date)->create(['title' => 'Malam Keakraban BEM']);
    Event::factory()->category(EventCategory::Meeting)->on($date)->create(['title' => 'Rapat Rahasia Internal']);

    foreach (['/kalender', '/'] as $url) {
        $this->get($url)->assertOk()->assertSee('Malam Keakraban BEM')->assertDontSee('Rapat Rahasia Internal');
    }
});

it('pengurus hanya melihat rapat divisinya, rapat umum, dan tugas yang ditugaskan padanya', function (): void {
    $anggotaKajian = member(RoleName::AnggotaKajian);
    $date = now()->addDays(5)->toDateString();

    $kajianMeeting = Event::factory()->category(EventCategory::Meeting)->on($date)->create(['division_id' => divisionId('kajian')]);
    $humasMeeting = Event::factory()->category(EventCategory::Meeting)->on($date)->create(['division_id' => divisionId('humas')]);
    $generalMeeting = Event::factory()->category(EventCategory::Meeting)->on($date)->create(['division_id' => null]);
    $public = Event::factory()->category(EventCategory::Ukm)->on($date)->create();
    $assigned = Event::factory()->category(EventCategory::Content)->on($date)->create(['division_id' => divisionId('medpub')]);
    $notAssigned = Event::factory()->category(EventCategory::Content)->on($date)->create(['division_id' => divisionId('medpub')]);
    EventAssignee::factory()->create(['event_id' => $assigned->id, 'user_id' => $anggotaKajian->id]);

    $visible = Event::query()->visibleTo($anggotaKajian)->pluck('id')->sort()->values()->all();

    expect($visible)->toBe(collect([$kajianMeeting, $generalMeeting, $public, $assigned])->pluck('id')->sort()->values()->all())
        ->and($visible)->not->toContain($humasMeeting->id)
        ->and($visible)->not->toContain($notAssigned->id);
});

it('super admin, pembina, dan sekretaris melihat semua rapat; divisi Medpub melihat semua tugas konten', function (): void {
    $events = oneEventPerCategory(now()->addDays(5)->toDateString());
    Event::factory()->category(EventCategory::Meeting)->create(['division_id' => divisionId('humas')]);
    $total = Event::count();

    expect(Event::query()->visibleTo(superAdmin())->count())->toBe($total)
        ->and(Event::query()->visibleTo(member(RoleName::Pembina))->count())->toBe($total);

    $sekretarisSees = Event::query()->visibleTo(member(RoleName::Sekretaris))->pluck('id');
    expect($sekretarisSees)->toContain($events['meeting']->id)->and($sekretarisSees)->not->toContain($events['content']->id);

    expect(Event::query()->visibleTo(member(RoleName::AnggotaMedpub))->pluck('id'))->toContain($events['content']->id);
});

it('data kalender dashboard tidak membocorkan rapat divisi lain', function (): void {
    $date = now()->startOfMonth()->addDays(10)->toDateString();
    Event::factory()->category(EventCategory::Meeting)->on($date)->create(['title' => 'Rapat Humas', 'division_id' => divisionId('humas')]);
    Event::factory()->category(EventCategory::Meeting)->on($date)->create(['title' => 'Rapat Kajian', 'division_id' => divisionId('kajian')]);

    actingAs(member(RoleName::KoordinatorKajian));
    $range = feedRange();

    $titles = collect(livewire(Calendar::class)->instance()->fetchEvents(
        $range['start'],
        $range['end'],
        array_map(fn (EventCategory $c) => $c->value, EventCategory::cases()),
    ))->pluck('title')->all();

    expect($titles)->toContain('Rapat Kajian')->not->toContain('Rapat Humas');
});

it('semua pengurus aktif bisa membuka Kalender; Tugas Saya tertutup untuk pembina', function (): void {
    foreach (RoleName::cases() as $role) {
        actingAs(member($role))->get(Calendar::getUrl())->assertOk();
    }

    actingAs(member(RoleName::AnggotaHumas))->get('/panel-bem/tugas-saya')->assertOk();
    actingAs(member(RoleName::Pembina))->get('/panel-bem/tugas-saya')->assertForbidden();
});
