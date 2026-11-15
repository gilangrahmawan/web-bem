<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Filament\Pages\Calendar;
use App\Models\Event;
use App\Policies\EventPolicy;
use App\Services\CalendarImportService;
use App\Services\EventService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/** Rasio kontras WCAG 2.x antara dua warna hex. */
function contrastRatio(string $a, string $b): float
{
    $luminance = function (string $hex): float {
        [$r, $g, $b] = array_map(
            fn (string $c): float => ($v = hexdec($c) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };

    [$light, $dark] = [max($luminance($a), $luminance($b)), min($luminance($a), $luminance($b))];

    return ($light + 0.05) / ($dark + 0.05);
}

// --- Warna -----------------------------------------------------------------------------------

it('warna kategori sesuai legenda BEM dan keputusan pemilik', function (EventCategory $category, string $bg, string $text, ?string $accent): void {
    expect($category->background())->toBe($bg)
        ->and($category->text())->toBe($text)
        ->and($category->accent())->toBe($accent);
})->with([
    'libur nasional' => [EventCategory::Holiday, '#FFABAB', '#9C0006', null],
    'cuti bersama' => [EventCategory::CollectiveLeave, '#F8CBAD', '#843C0C', null],
    'hari peringatan' => [EventCategory::Commemoration, '#FFE699', '#7F6000', null],
    'hari jadi daerah' => [EventCategory::RegionalAnniversary, '#A9E4EF', '#155A63', null],
    'kalender akademik' => [EventCategory::Campus, '#9BC2E6', '#1F3864', '#2F5597'],
    'HUT UBHINUS' => [EventCategory::UniversityAnniversary, '#1F3864', '#FFFFFF', null],
    'kegiatan BEM' => [EventCategory::Bem, '#B48FE0', '#3B1F5C', '#7030A0'],
    'UKM (tetap)' => [EventCategory::Ukm, '#15803D', '#FFFFFF', null],
    'rapat' => [EventCategory::Meeting, '#0F766E', '#FFFFFF', null],
    'tugas konten' => [EventCategory::Content, '#DB2777', '#FFFFFF', null],
    'internal (tetap)' => [EventCategory::Internal, '#64748B', '#FFFFFF', null],
]);

it('semua pasangan teks/latar kategori kontrasnya minimal 4,5:1 (terbaca di HP)', function (): void {
    foreach (EventCategory::cases() as $category) {
        expect(contrastRatio($category->text(), $category->background()))
            ->toBeGreaterThanOrEqual(4.5, "{$category->value}: ".round(contrastRatio($category->text(), $category->background()), 2));
    }
});

it('warna latar setiap kategori berbeda satu sama lain', function (): void {
    $backgrounds = array_map(fn (EventCategory $c) => $c->background(), EventCategory::cases());

    expect(array_unique($backgrounds))->toHaveCount(count(EventCategory::cases()));
});

it('feed kalender mengirim warna latar, teks, dan garis kiri', function (): void {
    Event::factory()->category(EventCategory::Campus)->on('2026-11-20')->create(['title' => 'UTS']);
    Event::factory()->category(EventCategory::Holiday)->on('2026-11-21')->create(['title' => 'Libur']);

    $events = collect(getJson(route('public.calendar.feed', ['start' => '2026-11-01', 'end' => '2026-12-01']))->json())->keyBy('title');

    expect($events['UTS'])->toMatchArray(['backgroundColor' => '#9BC2E6', 'textColor' => '#1F3864', 'borderColor' => '#2F5597'])
        ->and($events['UTS']['extendedProps']['accent'])->toBe('#2F5597')
        ->and($events['Libur'])->toMatchArray(['backgroundColor' => '#FFABAB', 'textColor' => '#9C0006'])
        ->and($events['Libur']['extendedProps']['accent'])->toBeNull();
});

it('legenda publik dan dashboard memuat semua kategori publik serta keterangan Minggu & Jumat', function (): void {
    $public = $this->get('/kalender')->assertOk();

    foreach (EventCategory::publicCases() as $category) {
        $public->assertSee($category->label());
    }
    $public->assertSee('Minggu')->assertSee('Jumat')->assertSee('Jatim, Kota Malang, Kab. Malang, Kota Batu');

    actingAs(superAdmin())->get(Calendar::getUrl())->assertOk()
        ->assertSee('Cuti Bersama')->assertSee('HUT UBHINUS')->assertSee('Rapat')->assertSee('Jumat');
});

// --- Izin ------------------------------------------------------------------------------------

it('semua nilai kategori muat di kolom events.category (MariaDB menolak string terlalu panjang)', function (): void {
    // SQLite di test tidak membatasi panjang string, jadi dijaga eksplisit di sini.
    $migration = require database_path('migrations/2026_11_18_090000_widen_events_category_column.php');

    foreach (EventCategory::cases() as $category) {
        expect(strlen($category->value))->toBeLessThanOrEqual($migration::LENGTH, $category->value);
    }
});

it('kategori baru publik dan tidak memicu cek bentrok', function (): void {
    foreach ([EventCategory::CollectiveLeave, EventCategory::RegionalAnniversary, EventCategory::UniversityAnniversary] as $category) {
        expect($category->isPublic())->toBeTrue()->and($category->checksConflict())->toBeFalse();
    }
});

it('cuti bersama tidak bisa dibuat, diubah, atau dihapus dari dashboard oleh siapa pun, termasuk super_admin', function (): void {
    $cuti = Event::factory()->category(EventCategory::CollectiveLeave)->on('2026-12-24')->create(['title' => 'Kelahiran Yesus Kristus']);

    foreach ([superAdmin(), member(RoleName::Sekretaris)] as $user) {
        $gate = Gate::forUser($user);

        expect($gate->allows('create', [Event::class, EventCategory::CollectiveLeave]))->toBeFalse()
            ->and($gate->allows('update', $cuti))->toBeFalse()
            ->and($gate->allows('delete', $cuti))->toBeFalse()
            ->and($gate->allows('view', $cuti))->toBeTrue()
            ->and(EventPolicy::creatableCategories($user))->not->toContain(EventCategory::CollectiveLeave);
    }

    $service = app(EventService::class);
    expect(fn () => $service->create(superAdmin(), ['title' => 'Cuti', 'category' => 'collective_leave', 'starts_at' => '2026-12-31']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $service->delete(superAdmin(), $cuti))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->update(superAdmin(), Event::factory()->category(EventCategory::Bem)->create(), [
            'title' => 'Diam-diam jadi cuti', 'category' => 'collective_leave', 'starts_at' => '2026-12-31',
        ]))->toThrow(AuthorizationException::class);

    expect(Event::whereKey($cuti->id)->exists())->toBeTrue();
});

it('super_admin tetap bisa mengelola kategori lain, termasuk Libur Nasional manual', function (): void {
    $admin = superAdmin();
    $service = app(EventService::class);

    foreach (['holiday', 'regional_anniversary', 'university_anniversary', 'bem', 'meeting', 'content'] as $category) {
        $data = ['title' => "Uji {$category}", 'category' => $category, 'starts_at' => '2026-12-01'];

        if ($category === 'content') {
            $data['assignee_ids'] = [member(RoleName::AnggotaMedpub)->id];
        }

        $event = $service->create($admin, $data);
        expect(Gate::forUser($admin)->allows('update', $event))->toBeTrue();
        $service->delete($admin, $event);
    }

    expect(Gate::forUser(member(RoleName::Sekretaris))->allows('create', [Event::class, EventCategory::Holiday]))->toBeTrue()
        ->and(Gate::forUser(member(RoleName::Sekretaris))->allows('create', [Event::class, EventCategory::RegionalAnniversary]))->toBeTrue()
        ->and(Gate::forUser(member(RoleName::KoordinatorMedpub))->allows('create', [Event::class, EventCategory::UniversityAnniversary]))->toBeFalse();
});

// --- Impor CSV -------------------------------------------------------------------------------

it('impor CSV menerima kategori hari jadi & HUT UBHINUS, menolak libur nasional & cuti bersama', function (): void {
    $rows = app(CalendarImportService::class)->parse("start_date,end_date,title,category,subcategory,source\n".implode("\n", [
        '2028-04-01,,Hari Jadi Kota Malang ke-114,regional_anniversary,,',
        '2028-01-10,,HUT UBHINUS ke-3,university_anniversary,,',
        '2028-01-01,,Tahun Baru,holiday,,',
        '2028-12-24,,Natal,collective_leave,,',
    ]));

    expect(array_column($rows, 'status'))->toBe(['valid', 'valid', 'invalid', 'invalid'])
        ->and(implode(' ', $rows[3]['errors']))->toContain('HolidaySeeder');
});

// --- Migration data --------------------------------------------------------------------------

it('migration data memindahkan cuti bersama, hari jadi, dan HUT UBHINUS lalu bisa di-rollback', function (): void {
    $make = fn (string $category, string $title) => Event::factory()->category(EventCategory::from($category))
        ->on('2026-12-01')->create(['title' => $title]);

    $cuti = $make('holiday', 'Idulfitri (Cuti Bersama)');
    $libur = $make('holiday', 'Idulfitri 1447 H');
    $hariJadi = $make('commemoration', 'Hari Jadi Kota Malang ke-113');
    $peringatan = $make('commemoration', 'Hari Pahlawan');
    $hut = $make('campus', 'HUT UBHINUS ke-2');
    $uts = $make('campus', 'UTS Semester Ganjil');
    $bem = $make('bem', 'Hari Jadi BEM (Cuti Bersama)'); // kategori lain: tidak boleh tersentuh

    $migration = require database_path('migrations/2026_11_18_100000_split_calendar_legend_categories.php');
    $migration->up();

    $state = fn () => collect([$cuti, $libur, $hariJadi, $peringatan, $hut, $uts, $bem])
        ->map(fn (Event $e) => [$e->fresh()->category->value, $e->fresh()->title])->all();

    expect($state())->toBe([
        ['collective_leave', 'Idulfitri'],
        ['holiday', 'Idulfitri 1447 H'],
        ['regional_anniversary', 'Hari Jadi Kota Malang ke-113'],
        ['commemoration', 'Hari Pahlawan'],
        ['university_anniversary', 'HUT UBHINUS ke-2'],
        ['campus', 'UTS Semester Ganjil'],
        ['bem', 'Hari Jadi BEM (Cuti Bersama)'],
    ]);

    $migration->down();

    expect($state())->toBe([
        ['holiday', 'Idulfitri (Cuti Bersama)'],
        ['holiday', 'Idulfitri 1447 H'],
        ['commemoration', 'Hari Jadi Kota Malang ke-113'],
        ['commemoration', 'Hari Pahlawan'],
        ['campus', 'HUT UBHINUS ke-2'],
        ['campus', 'UTS Semester Ganjil'],
        ['bem', 'Hari Jadi BEM (Cuti Bersama)'],
    ]);
});
