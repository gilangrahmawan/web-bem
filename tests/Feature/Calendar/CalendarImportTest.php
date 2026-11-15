<?php

use App\Enums\EventCategory;
use App\Enums\RoleName;
use App\Filament\Pages\Calendar;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Services\CalendarImportService;
use App\Services\EventService;
use Database\Seeders\CalendarCsvSeeder;
use Database\Seeders\HolidaySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

const CSV_HEADER = "start_date,end_date,title,category,subcategory,source\n";

function importer(): CalendarImportService
{
    return app(CalendarImportService::class);
}

/** @return array<string, string> status per judul */
function statuses(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (array $r) => [$r['title'] ?: "baris {$r['line']}" => $r['status']])->all();
}

// --- Kategori Hari Peringatan --------------------------------------------------------------

it('kategori Hari Peringatan publik, berwarna sendiri, dan tidak memicu bentrok', function (): void {
    expect(EventCategory::Commemoration->isPublic())->toBeTrue()
        ->and(EventCategory::Commemoration->checksConflict())->toBeFalse()
        ->and(EventCategory::Commemoration->hex())->not->toBe(EventCategory::Holiday->hex())
        ->and(EventCategory::Commemoration->label())->toBe('Hari Peringatan');

    $service = app(EventService::class);
    $a = $service->create(superAdmin(), ['title' => 'Hari Pahlawan', 'category' => 'commemoration', 'starts_at' => '2026-11-10']);
    $ukm = $service->create(superAdmin(), ['title' => 'Lomba UKM', 'category' => 'ukm', 'starts_at' => '2026-11-10']);

    expect($a->fresh()->has_conflict)->toBeFalse()->and($ukm->fresh()->has_conflict)->toBeFalse();

    $categories = collect(getJson(route('public.calendar.feed', ['start' => '2026-11-01', 'end' => '2026-12-01']))->json())
        ->pluck('extendedProps.category');
    expect($categories)->toContain('commemoration');
});

it('Hari Peringatan hanya dikelola super_admin & sekretaris; Koordinator Medpub bisa melihatnya', function (): void {
    $event = Event::factory()->category(EventCategory::Commemoration)->create();

    foreach ([RoleName::SuperAdmin, RoleName::Sekretaris] as $role) {
        expect(Gate::forUser(member($role))->allows('create', [Event::class, EventCategory::Commemoration]))->toBeTrue();
    }

    $koorMedpub = member(RoleName::KoordinatorMedpub);
    expect(Gate::forUser($koorMedpub)->allows('create', [Event::class, EventCategory::Commemoration]))->toBeFalse()
        ->and(Gate::forUser($koorMedpub)->allows('update', $event))->toBeFalse()
        ->and(Gate::forUser($koorMedpub)->allows('view', $event))->toBeTrue()
        ->and(Event::query()->visibleTo($koorMedpub)->pluck('id'))->toContain($event->id);
});

// --- Parser & validasi ---------------------------------------------------------------------

it('menolak file dengan header salah, kosong, atau tanpa baris data', function (string $csv): void {
    expect(fn () => importer()->parse($csv))->toThrow(ValidationException::class);
})->with([
    'kosong' => [''],
    'header salah' => ["tanggal,judul\n2026-11-10,Hari Pahlawan\n"],
    'kolom kurang' => ["start_date,end_date,title,category\n2026-11-10,,Hari Pahlawan,commemoration\n"],
    'hanya header' => [CSV_HEADER],
]);

it('memvalidasi setiap baris dan menjelaskan alasannya', function (): void {
    $rows = importer()->parse(CSV_HEADER.implode("\n", [
        '2026-11-10,2026-11-10,Hari Pahlawan,commemoration,Hari Peringatan Nasional,Keppres',
        '2026-12-24,2027-01-02,Libur Akademik,campus,,',
        '2026-02-30,,Tanggal Mustahil,campus,,',
        '10/11/2026,,Format Salah,campus,,',
        '2026-11-12,2026-11-11,Terbalik,campus,,',
        ',,,campus,,',
        '2026-08-17,,HUT RI,holiday,,',
        '2026-11-15,,Rapat Rahasia,meeting,,',
        '2026-11-16,,Kategori Ngawur,festival,,',
    ]));

    expect(statuses($rows))->toBe([
        'Hari Pahlawan' => 'valid',
        'Libur Akademik' => 'valid',
        'Tanggal Mustahil' => 'invalid',
        'Format Salah' => 'invalid',
        'Terbalik' => 'invalid',
        'baris 7' => 'invalid',
        'HUT RI' => 'invalid',
        'Rapat Rahasia' => 'invalid',
        'Kategori Ngawur' => 'invalid',
    ]);

    $holiday = collect($rows)->firstWhere('title', 'HUT RI');
    expect(implode(' ', $holiday['errors']))->toContain('HolidaySeeder');
    expect($rows[0]['line'])->toBe(2);
});

it('membaca BOM UTF-8, pemisah titik koma, urutan kolom bebas, dan nilai berkutip', function (): void {
    $csv = "\xEF\xBB\xBF".'title;category;start_date;end_date;source;subcategory'."\n"
        .'"Hari Guru; Nasional";commemoration;2026-11-25;;Keppres;Hari Peringatan Nasional'."\n";

    $row = importer()->parse($csv)[0];

    expect($row['status'])->toBe('valid')
        ->and($row['title'])->toBe('Hari Guru; Nasional')
        ->and($row['start_date'])->toBe('2026-11-25')
        ->and($row['source'])->toBe('Keppres');
});

it('menandai duplikat terhadap database dan di dalam file yang sama', function (): void {
    Event::factory()->category(EventCategory::Commemoration)->on('2026-11-10')->create(['title' => 'Hari Pahlawan']);

    $rows = importer()->parse(CSV_HEADER.implode("\n", [
        '2026-11-10,,hari pahlawan ,commemoration,,',   // beda huruf/spasi → tetap duplikat
        '2026-11-25,,Hari Guru Nasional,commemoration,,',
        '2026-11-25,,Hari Guru Nasional,commemoration,,', // dobel di file
        '2027-11-10,,Hari Pahlawan,commemoration,,',      // tanggal lain → bukan duplikat
    ]));

    expect(array_column($rows, 'status'))->toBe(['duplicate', 'valid', 'duplicate', 'valid'])
        ->and(CalendarImportService::counts($rows))->toBe(['valid' => 2, 'duplicate' => 2, 'invalid' => 0]);
});

// --- Seeder --------------------------------------------------------------------------------

it('CalendarCsvSeeder memuat 55 agenda dari CSV tanpa libur nasional', function (): void {
    $this->seed(CalendarCsvSeeder::class);

    expect(Event::count())->toBe(55)
        ->and(Event::where('category', EventCategory::Holiday->value)->count())->toBe(0)
        ->and(Event::where('all_day', false)->count())->toBe(0)
        ->and(Event::where('category', 'commemoration')->count())->toBe(38)
        ->and(Event::where('category', 'campus')->count())->toBe(17);

    $uts = Event::where('title', 'UTS Semester Ganjil')->firstOrFail();
    expect($uts->starts_at->toDateString())->toBe('2026-10-26')
        ->and($uts->ends_at->toDateString())->toBe('2026-10-29')
        ->and($uts->description)->toBe("Subkategori: Kalender Akademik UBHINUS\nSumber: Kalender Akademik & Kalender Maba UBHINUS 2026/2027");

    expect(Event::where('title', 'Hari Pahlawan')->value('ends_at'))->toBeNull();
});

it('CalendarCsvSeeder insert-only: dijalankan ulang tetap 55 agenda dan editan dashboard tidak ditimpa', function (): void {
    $this->seed(CalendarCsvSeeder::class);

    $event = Event::where('title', 'Hari Pahlawan')->firstOrFail();
    $event->update(['description' => 'Diedit lewat dashboard', 'location' => 'Alun-alun Malang', 'ends_at' => '2026-11-11']);

    $this->seed(CalendarCsvSeeder::class);

    $event->refresh();
    expect(Event::count())->toBe(55)
        ->and($event->description)->toBe('Diedit lewat dashboard')
        ->and($event->location)->toBe('Alun-alun Malang')
        ->and($event->ends_at->toDateString())->toBe('2026-11-11');
});

it('CSV kalender tidak memuat libur nasional/cuti bersama dan tidak bertabrakan dengan HolidaySeeder', function (): void {
    $rows = importer()->parse((string) file_get_contents(database_path(CalendarCsvSeeder::FILE)));

    expect(collect($rows)->pluck('category')->unique()->sort()->values()->all())->toBe(['campus', 'commemoration'])
        ->and(collect($rows)->filter(fn ($r) => str_contains(mb_strtolower($r['title']), 'cuti bersama')))->toBeEmpty();

    $this->seed([HolidaySeeder::class, CalendarCsvSeeder::class]);

    expect(Event::where('category', 'holiday')->count())->toBe(51)
        ->and(Event::where('category', '!=', 'holiday')->count())->toBe(55);
});

// --- Impor lewat dashboard -----------------------------------------------------------------

function csvUpload(string $contents, string $name = 'kalender-2027.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('impor dashboard menampilkan pratinjau dulu, lalu hanya menyimpan baris valid', function (): void {
    Event::factory()->category(EventCategory::Campus)->on('2027-10-26')->create(['title' => 'UTS Ganjil 2027']);
    $sekretaris = member(RoleName::Sekretaris);
    actingAs($sekretaris);

    $page = livewire(Calendar::class)
        ->callAction('importCsv', data: ['file' => csvUpload(CSV_HEADER.implode("\n", [
            '2027-10-26,2027-10-29,UTS Ganjil 2027,campus,Kalender Akademik UBHINUS,Kalender 2027/2028',
            '2027-12-08,,Wisuda 2027,campus,Kalender Akademik UBHINUS,Kalender 2027/2028',
            '2027-11-10,,Hari Pahlawan,commemoration,Hari Peringatan Nasional,Keppres',
            '2027-08-17,,HUT RI,holiday,,',
        ]))])
        ->assertActionMounted('confirmImport');

    $preview = $page->instance()->importPreview;
    expect($preview['file'])->toBe('kalender-2027.csv')
        ->and(CalendarImportService::counts($preview['rows']))->toBe(['valid' => 2, 'duplicate' => 1, 'invalid' => 1])
        ->and(Event::count())->toBe(1); // belum ada yang disimpan

    $page->callMountedAction()->assertHasNoFormErrors();

    expect(Event::count())->toBe(3)
        ->and(Event::where('title', 'Wisuda 2027')->value('created_by'))->toBe($sekretaris->id)
        ->and(Event::where('category', 'holiday')->exists())->toBeFalse()
        ->and($page->instance()->importPreview)->toBeNull();

    $this->assertDatabaseHas('activity_logs', ['action' => 'event.imported', 'user_id' => $sekretaris->id]);
    $log = ActivityLog::where('action', 'event.imported')->firstOrFail();
    expect($log->properties)->toMatchArray(['file' => 'kalender-2027.csv', 'created' => 2, 'duplicate' => 1, 'invalid' => 1]);
});

it('impor dashboard menolak file yang header-nya salah tanpa membuka pratinjau', function (): void {
    actingAs(member(RoleName::Sekretaris));

    livewire(Calendar::class)
        ->callAction('importCsv', data: ['file' => csvUpload("tanggal,judul\n2027-01-01,Tahun Baru\n")])
        ->assertActionNotMounted('confirmImport');

    expect(Event::count())->toBe(0);
});

it('impor CSV hanya untuk super_admin dan sekretaris', function (): void {
    foreach (RoleName::cases() as $role) {
        $allowed = in_array($role, [RoleName::SuperAdmin, RoleName::Sekretaris], true);
        expect(Gate::forUser(member($role))->allows('import', Event::class))->toBe($allowed, $role->value);
    }

    actingAs(member(RoleName::KoordinatorHumas));
    livewire(Calendar::class)->assertActionHidden('importCsv');

    $rows = importer()->parse(CSV_HEADER."2027-11-10,,Hari Pahlawan,commemoration,,\n");
    expect(fn () => importer()->import(member(RoleName::KoordinatorMedpub), $rows, 'x.csv'))->toThrow(AuthorizationException::class)
        ->and(fn () => importer()->import(member(RoleName::Pembina), $rows, 'x.csv'))->toThrow(AuthorizationException::class);
    expect(Event::count())->toBe(0);
});

it('impor memvalidasi ulang baris saat disimpan (data pratinjau tidak dipercaya mentah)', function (): void {
    $rows = importer()->parse(CSV_HEADER."2027-11-10,,Hari Pahlawan,commemoration,,\n");
    $rows[0]['category'] = 'holiday'; // dimanipulasi setelah pratinjau

    $summary = importer()->import(superAdmin(), $rows, 'x.csv');

    expect($summary)->toBe(['created' => 0, 'duplicate' => 0, 'invalid' => 1])
        ->and(Event::count())->toBe(0);
});

it('agenda UKM/BEM hasil impor ikut diperiksa bentroknya', function (): void {
    $existing = app(EventService::class)->create(superAdmin(), ['title' => 'Pentas Seni', 'category' => 'ukm', 'starts_at' => '2027-03-10']);

    importer()->import(superAdmin(), importer()->parse(CSV_HEADER."2027-03-10,,Bazar UKM,ukm,,\n"), 'x.csv');

    expect($existing->fresh()->has_conflict)->toBeTrue()
        ->and(Event::where('title', 'Bazar UKM')->value('has_conflict'))->toBeTrue();
});
