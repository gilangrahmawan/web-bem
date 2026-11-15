<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\Event;
use Database\Seeders\CalendarCsvSeeder;
use Database\Seeders\HolidaySeeder;
use Illuminate\Database\Eloquent\Builder;

const SEEDER_CATEGORIES = ['holiday', 'collective_leave'];

function seededHolidays(): Builder
{
    return Event::whereIn('category', SEEDER_CATEGORIES);
}

function holidaysInYear(int $year, ?string $category = null): int
{
    return seededHolidays()
        ->when($category, fn ($q) => $q->where('category', $category))
        ->whereBetween('starts_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
        ->count();
}

/** Potret semua agenda di luar holiday/collective_leave (untuk memastikan tidak ada yang berubah). */
function nonHolidaySnapshot(): array
{
    return Event::whereNotIn('category', SEEDER_CATEGORIES)->orderBy('id')->get()
        ->map(fn (Event $e) => $e->only(['id', 'title', 'category', 'starts_at', 'ends_at', 'description', 'created_by', 'updated_at']))
        ->map(fn (array $a) => array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('c') : ($v instanceof BackedEnum ? $v->value : $v), $a))
        ->all();
}

it('memuat libur resmi SKB: 25 di 2026 dan 26 di 2027 (total 51), semuanya seharian', function (): void {
    $this->seed(HolidaySeeder::class);

    expect(seededHolidays()->count())->toBe(51)
        ->and(holidaysInYear(2026))->toBe(25)
        ->and(holidaysInYear(2027))->toBe(26)
        ->and(holidaysInYear(2026, 'holiday'))->toBe(17)
        ->and(holidaysInYear(2026, 'collective_leave'))->toBe(8)
        ->and(holidaysInYear(2027, 'holiday'))->toBe(18)
        ->and(holidaysInYear(2027, 'collective_leave'))->toBe(8)
        ->and(seededHolidays()->where('all_day', false)->count())->toBe(0)
        ->and(seededHolidays()->where('visibility', '!=', 'public')->count())->toBe(0)
        ->and(Event::where('title', 'like', '%(Cuti Bersama)%')->count())->toBe(0);
});

it('judul berulang di tanggal berbeda tetap tersimpan semua (bukan duplikat)', function (): void {
    $this->seed(HolidaySeeder::class);

    $dates = fn (string $title, string $category = 'holiday') => Event::where('category', $category)->where('title', $title)
        ->orderBy('starts_at')->get()->map(fn (Event $e) => $e->starts_at->toDateString())->all();

    expect($dates('Idulfitri 1447 H'))->toBe(['2026-03-21', '2026-03-22'])
        ->and($dates('Idulfitri', 'collective_leave'))->toBe(['2026-03-20', '2026-03-23', '2026-03-24', '2027-03-09', '2027-03-12', '2027-03-15'])
        ->and($dates('Kelahiran Yesus Kristus'))->toBe(['2026-12-25', '2027-12-25'])
        ->and($dates('Kelahiran Yesus Kristus', 'collective_leave'))->toBe(['2026-12-24', '2027-12-24'])
        ->and($dates('Isra Mikraj Nabi Muhammad SAW 1448 H'))->toBe(['2027-01-05'])
        ->and($dates('Isra Mikraj Nabi Muhammad SAW 1449 H'))->toBe(['2027-12-26']);
});

it('idempoten: dijalankan ulang tetap 51 dan tidak ada yang diperbarui', function (): void {
    $this->seed(HolidaySeeder::class);
    $before = seededHolidays()->orderBy('id')->get(['id', 'updated_at'])->toArray();

    $this->seed(HolidaySeeder::class);

    expect(seededHolidays()->orderBy('id')->get(['id', 'updated_at'])->toArray())->toBe($before);
});

it('upsert: deskripsi & lokasi dari pengurus tetap, field kelolaan seeder dikembalikan', function (): void {
    $this->seed(HolidaySeeder::class);

    $natal = Event::where('category', 'holiday')->where('title', 'Kelahiran Yesus Kristus')->whereYear('starts_at', 2026)->firstOrFail();
    $natal->update(['organizer' => 'Diubah', 'all_day' => false, 'description' => 'Catatan pengurus', 'location' => 'Gereja']);

    $this->seed(HolidaySeeder::class);

    $natal->refresh();
    expect($natal->organizer)->toBe('Pemerintah RI')
        ->and($natal->all_day)->toBeTrue()
        ->and($natal->description)->toBe('Catatan pengurus')
        ->and($natal->location)->toBe('Gereja');
});

it('menghapus entri perkiraan lama yang tidak ada di daftar SKB', function (): void {
    foreach ([['2026-03-21', 'Hari Raya Idul Fitri 1447 H (hari pertama)'], ['2027-01-06', 'Isra Mikraj Nabi Muhammad SAW 1448 H']] as [$date, $title]) {
        Event::factory()->category(EventCategory::Holiday)->on($date)->create(['title' => $title, 'created_by' => null]);
    }
    Event::factory()->category(EventCategory::CollectiveLeave)->on('2026-12-31')->create(['title' => 'Cuti lama', 'created_by' => null]);

    $this->seed(HolidaySeeder::class);

    expect(Event::where('title', 'Hari Raya Idul Fitri 1447 H (hari pertama)')->exists())->toBeFalse()
        ->and(Event::where('title', 'Cuti lama')->exists())->toBeFalse()
        ->and(Event::where('title', 'Isra Mikraj Nabi Muhammad SAW 1448 H')->pluck('starts_at')->map->toDateString()->all())->toBe(['2027-01-05'])
        ->and(seededHolidays()->count())->toBe(51);

    $this->assertDatabaseHas('activity_logs', ['action' => 'holiday.synced']);
});

it('tidak mengubah agenda kategori lain maupun libur nasional yang dibuat lewat dashboard', function (): void {
    $this->seed(CalendarCsvSeeder::class); // commemoration, regional_anniversary, campus, university_anniversary

    foreach (['bem', 'ukm', 'meeting', 'content', 'internal', 'commemoration'] as $category) {
        // Judul & tanggal sengaja sama dengan libur resmi.
        Event::factory()->category(EventCategory::from($category))->on('2026-12-25')->create([
            'title' => 'Kelahiran Yesus Kristus',
            'created_by' => null,
        ]);
    }

    $manualHoliday = Event::factory()->category(EventCategory::Holiday)->on('2026-12-31')->create([
        'title' => 'Libur Akhir Tahun Kampus (input manual)',
        'created_by' => member(RoleName::Sekretaris)->id,
    ]);

    $before = nonHolidaySnapshot();

    $this->seed(HolidaySeeder::class);
    $this->seed(HolidaySeeder::class);

    expect(nonHolidaySnapshot())->toBe($before)
        ->and(Event::whereKey($manualHoliday->id)->exists())->toBeTrue()
        ->and(seededHolidays()->whereNull('created_by')->count())->toBe(51);
});
