<?php

namespace App\Services;

use App\Enums\EventCategory;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Impor agenda kalender dari CSV (kalender akademik, hari peringatan, dll.).
 *
 * Format kolom: start_date, end_date, title, category, subcategory, source
 * (urutan bebas; pemisah koma atau titik koma; BOM UTF-8 diabaikan).
 *
 * Aturan:
 * - Semua agenda seharian (all_day); end_date kosong = satu hari.
 * - Kategori hanya campus, commemoration, bem, ukm. `holiday` ditolak:
 *   libur nasional & cuti bersama hanya dari HolidaySeeder.
 * - Duplikat (tanggal mulai + judul sama, tanpa beda huruf besar/kecil) selalu dilewati —
 *   baik terhadap agenda yang sudah ada maupun baris lain di file yang sama.
 *   Agenda yang sudah diedit lewat dashboard tidak pernah ditimpa.
 */
class CalendarImportService
{
    public const COLUMNS = ['start_date', 'end_date', 'title', 'category', 'subcategory', 'source'];

    public const MAX_ROWS = 1000;

    public const STATUS_VALID = 'valid';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        private readonly EventConflictService $conflicts,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Baca & validasi CSV menjadi pratinjau per baris (belum menyimpan apa pun).
     *
     * @return list<array{line: int, start_date: string, end_date: string, title: string, category: string,
     *     subcategory: string, source: string, status: string, errors: list<string>}>
     *
     * @throws ValidationException bila file tidak bisa dibaca (header salah, kosong, terlalu banyak baris)
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? '';

        if (trim($contents) === '') {
            throw ValidationException::withMessages(['file' => 'File CSV kosong.']);
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'File harus berenkoding UTF-8.']);
        }

        $firstLine = strtok($contents, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), fgetcsv($handle, null, $delimiter, '"', '') ?: []);
        $missing = array_diff(self::COLUMNS, $header);

        if ($missing !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Header harus: '.implode(',', self::COLUMNS).'.',
            ]);
        }

        $index = array_flip($header);
        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;

            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue; // baris kosong
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per impor.']);
            }

            $value = fn (string $column): string => trim((string) ($cells[$index[$column]] ?? ''));

            $rows[] = $this->validateRow([
                'line' => $line,
                'start_date' => $value('start_date'),
                'end_date' => $value('end_date'),
                'title' => preg_replace('/\s+/u', ' ', $value('title')) ?? '',
                'category' => strtolower($value('category')),
                'subcategory' => $value('subcategory'),
                'source' => $value('source'),
            ]);
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris data di bawah header.']);
        }

        return $this->markDuplicates($rows);
    }

    /**
     * Simpan baris valid. Validasi & cek duplikat diulang di sini (data bisa berubah sejak pratinjau).
     *
     * @param  list<array<string, mixed>>  $rows  hasil parse()
     * @param  User|null  $actor  null = seeder/console (tanpa cek izin)
     * @return array{created: int, duplicate: int, invalid: int}
     *
     * @throws AuthorizationException
     */
    public function import(?User $actor, array $rows, string $fileName): array
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('import', Event::class);
        }

        $rows = $this->markDuplicates(array_map(fn (array $row): array => $this->validateRow($row), $rows));
        $valid = array_values(array_filter($rows, fn (array $row): bool => $row['status'] === self::STATUS_VALID));

        if ($actor !== null) {
            foreach (array_unique(array_column($valid, 'category')) as $category) {
                Gate::forUser($actor)->authorize('create', [Event::class, EventCategory::from($category)]);
            }
        }

        $summary = [
            'created' => count($valid),
            'duplicate' => count(array_filter($rows, fn (array $row): bool => $row['status'] === self::STATUS_DUPLICATE)),
            'invalid' => count(array_filter($rows, fn (array $row): bool => $row['status'] === self::STATUS_INVALID)),
        ];

        DB::transaction(function () use ($actor, $valid, $fileName, $summary): void {
            $conflictRanges = [];

            foreach ($valid as $row) {
                $category = EventCategory::from($row['category']);
                $start = CarbonImmutable::parse($row['start_date'])->startOfDay();
                $end = filled($row['end_date']) ? CarbonImmutable::parse($row['end_date'])->startOfDay() : null;

                Event::query()->create([
                    'title' => $row['title'],
                    'description' => $this->description($row),
                    'category' => $category,
                    'visibility' => $category->visibility(),
                    'starts_at' => $start,
                    'ends_at' => $end !== null && $end->greaterThan($start) ? $end : null,
                    'all_day' => true,
                    'created_by' => $actor?->getKey(),
                ]);

                if ($category->checksConflict()) {
                    $conflictRanges[] = [$start, $end];
                }
            }

            if ($conflictRanges !== []) {
                $this->conflicts->refresh($conflictRanges);
            }

            $this->logger->log('event.imported', null, [
                'file' => $fileName,
                ...$summary,
            ], $actor);
        });

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{valid: int, duplicate: int, invalid: int}
     */
    public static function counts(array $rows): array
    {
        $statuses = array_count_values(array_column($rows, 'status'));

        return [
            'valid' => $statuses[self::STATUS_VALID] ?? 0,
            'duplicate' => $statuses[self::STATUS_DUPLICATE] ?? 0,
            'invalid' => $statuses[self::STATUS_INVALID] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function validateRow(array $row): array
    {
        $row = [
            'line' => (int) ($row['line'] ?? 0),
            ...array_map(fn ($v) => trim((string) $v), array_intersect_key($row, array_flip(self::COLUMNS))),
        ];
        $row += array_fill_keys(self::COLUMNS, '');

        $errors = [];
        $start = $this->strictDate($row['start_date']);
        $end = $row['end_date'] === '' ? null : $this->strictDate($row['end_date']);

        if ($start === null) {
            $errors[] = 'start_date wajib berformat YYYY-MM-DD (tanggal valid).';
        }

        if ($row['end_date'] !== '' && $end === null) {
            $errors[] = 'end_date harus berformat YYYY-MM-DD atau dikosongkan.';
        }

        if ($start !== null && $end !== null && $end->lessThan($start)) {
            $errors[] = 'end_date tidak boleh sebelum start_date.';
        }

        if ($row['title'] === '') {
            $errors[] = 'title wajib diisi.';
        } elseif (mb_strlen($row['title']) > 200) {
            $errors[] = 'title maksimal 200 karakter.';
        }

        $category = EventCategory::tryFrom($row['category']);

        if ($category === EventCategory::Holiday) {
            $errors[] = 'Kategori holiday tidak boleh diimpor: libur nasional & cuti bersama hanya dari HolidaySeeder.';
        } elseif (! in_array($category, EventCategory::importableCases(), true)) {
            $errors[] = 'category harus salah satu dari: '.implode(', ', array_map(fn (EventCategory $c) => $c->value, EventCategory::importableCases())).'.';
        }

        foreach (['subcategory', 'source'] as $column) {
            if (mb_strlen($row[$column]) > 150) {
                $errors[] = "{$column} maksimal 150 karakter.";
            }
        }

        return [...$row, 'status' => $errors === [] ? self::STATUS_VALID : self::STATUS_INVALID, 'errors' => $errors];
    }

    /**
     * Tandai baris valid yang sudah ada di database / muncul lebih dulu di file.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function markDuplicates(array $rows): array
    {
        $dates = array_column(array_filter($rows, fn (array $r): bool => $r['status'] === self::STATUS_VALID), 'start_date');

        $existing = [];

        if ($dates !== []) {
            Event::query()
                ->whereBetween('starts_at', [
                    CarbonImmutable::parse(min($dates))->startOfDay(),
                    CarbonImmutable::parse(max($dates))->endOfDay(),
                ])
                ->get(['title', 'starts_at'])
                ->each(function (Event $event) use (&$existing): void {
                    $existing[$this->key($event->starts_at->toDateString(), $event->title)] = true;
                });
        }

        foreach ($rows as $i => $row) {
            if ($row['status'] !== self::STATUS_VALID) {
                continue;
            }

            $key = $this->key($row['start_date'], $row['title']);

            if (isset($existing[$key])) {
                $rows[$i]['status'] = self::STATUS_DUPLICATE;
                $rows[$i]['errors'] = ['Sudah ada agenda dengan tanggal & judul yang sama — dilewati.'];

                continue;
            }

            $existing[$key] = true;
        }

        return $rows;
    }

    private function key(string $date, string $title): string
    {
        return $date.'|'.mb_strtolower(trim($title));
    }

    private function strictDate(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === null || $date->format('Y-m-d') !== $value || $date->year < 2000 || $date->year > 2100) {
            return null;
        }

        return $date;
    }

    /** @param  array<string, mixed>  $row */
    private function description(array $row): ?string
    {
        $lines = array_filter([
            filled($row['subcategory']) ? 'Subkategori: '.$row['subcategory'] : null,
            filled($row['source']) ? 'Sumber: '.$row['source'] : null,
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }
}
