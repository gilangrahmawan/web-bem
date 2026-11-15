<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Models\Event;
use Database\Seeders\CalendarCsvSeeder;
use Database\Seeders\HolidaySeeder;

it('HolidaySeeder idempoten: dijalankan ulang tetap 34 libur', function (): void {
    $this->seed(HolidaySeeder::class);
    $this->seed(HolidaySeeder::class);

    expect(Event::where('category', 'holiday')->count())->toBe(34);
});

it('upsert: tanggal libur milik seeder dikembalikan sesuai daftar, deskripsi & lokasi tidak disentuh', function (): void {
    $this->seed(HolidaySeeder::class);

    $natal = Event::where('title', 'Hari Raya Natal 2026')->firstOrFail();
    $natal->update(['starts_at' => '2026-12-20', 'description' => 'Catatan pengurus', 'location' => 'Gereja']);

    $this->seed(HolidaySeeder::class);

    $natal->refresh();
    expect($natal->starts_at->toDateString())->toBe('2026-12-25')
        ->and($natal->description)->toBe('Catatan pengurus')
        ->and($natal->location)->toBe('Gereja');
});

it('menghapus libur perkiraan lama milik seeder yang tidak ada lagi di daftar', function (): void {
    Event::factory()->category(EventCategory::Holiday)->on('2027-03-09')->create([
        'title' => 'Hari Raya Idul Fitri 1448 H (perkiraan lama)',
        'created_by' => null,
    ]);

    $this->seed(HolidaySeeder::class);

    expect(Event::where('title', 'Hari Raya Idul Fitri 1448 H (perkiraan lama)')->exists())->toBeFalse()
        ->and(Event::where('category', 'holiday')->count())->toBe(34);

    $this->assertDatabaseHas('activity_logs', ['action' => 'holiday.synced']);
});

it('judul libur ganda milik seeder dirapikan menjadi satu', function (): void {
    $this->seed(HolidaySeeder::class);
    Event::factory()->category(EventCategory::Holiday)->on('2026-08-17')->create([
        'title' => 'Hari Kemerdekaan Republik Indonesia ke-81',
        'created_by' => null,
    ]);

    $this->seed(HolidaySeeder::class);

    expect(Event::where('title', 'Hari Kemerdekaan Republik Indonesia ke-81')->count())->toBe(1);
});

it('tidak menyentuh agenda kategori lain maupun libur yang dibuat lewat dashboard', function (): void {
    $this->seed(CalendarCsvSeeder::class); // commemoration & campus, juga created_by = null
    $csvCount = Event::where('category', '!=', 'holiday')->count();

    $sekretaris = member(RoleName::Sekretaris);
    $manualHoliday = Event::factory()->category(EventCategory::Holiday)->on('2026-12-31')->create([
        'title' => 'Cuti Bersama Akhir Tahun (input manual)',
        'created_by' => $sekretaris->id,
    ]);
    // Agenda non-holiday tanpa pembuat dengan judul persis sama dengan salah satu libur.
    $lookalike = Event::factory()->category(EventCategory::Commemoration)->on('2026-11-01')->create([
        'title' => 'Hari Raya Natal 2026',
        'created_by' => null,
    ]);

    $this->seed(HolidaySeeder::class);

    expect(Event::where('category', '!=', 'holiday')->count())->toBe($csvCount + 1)
        ->and(Event::whereKey($manualHoliday->id)->exists())->toBeTrue()
        ->and($lookalike->fresh()->starts_at->toDateString())->toBe('2026-11-01')
        ->and($lookalike->fresh()->category)->toBe(EventCategory::Commemoration);
});
