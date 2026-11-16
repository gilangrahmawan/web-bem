<?php

namespace App\Services\Transfer;

use App\Enums\PostStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Pindah data penting lokal → produksi (Fase 10, keputusan pemilik) TANPA akun & konten demo.
 *
 * Ikut: pengaturan website (bukan teknis), divisi, periode, struktur (+foto), proker, agenda kalender,
 * organisasi, template dokumen, berita/kajian terbit (+cover, lampiran), galeri (+foto), unduhan (+berkas).
 * TIDAK ikut: semua akun (relasi ke akun dikosongkan), data demo ("Contoh"/contoh-*, @demo.test),
 * kas, aspirasi, chat, drive, oprec, notifikasi, log, setting teknis (kas_start_date, kuota, retensi, dll.).
 *
 * Import idempoten: dicocokkan lewat kunci alami (slug, nama, judul+tanggal), aman dijalankan ulang.
 * Memakai query builder (bukan event model) agar tidak ada efek samping (mis. menghapus foto lama).
 */
class DataTransferService
{
    public const FORMAT = 'bem-data-v1';

    /** Pengaturan website yang ikut dipindah. Selain ini dianggap teknis & diatur ulang di produksi. */
    public const WEBSITE_SETTINGS = [
        'cabinet_name', 'vision', 'mission', 'leader_welcome', 'history', 'logo_philosophy',
        'hero_title', 'hero_subtitle', 'hero_image', 'maps_embed_url',
        'contact_email', 'contact_phone', 'contact_address',
        'social_instagram', 'social_tiktok', 'social_youtube',
    ];

    public const DEMO_EMAIL = '%@demo.test';

    /** @var array<string, array{disk: string, path: string}> */
    private array $files = [];

    // =================================================================== EXPORT

    /** @return array{path: string, counts: array<string, int>} */
    public function export(string $target): array
    {
        $this->files = [];
        $data = $this->collect();

        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Tidak bisa membuat berkas {$target}.");
        }

        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        foreach ($this->files as ['disk' => $disk, 'path' => $path]) {
            $storage = Storage::disk($disk);

            if ($storage->exists($path)) {
                $zip->addFile($storage->path($path), "files/{$disk}/{$path}");
            }
        }

        $zip->close();

        return ['path' => $target, 'counts' => array_map(fn ($rows) => is_array($rows) ? count($rows) : 0, $data['tables'])];
    }

    /** @return array<string, mixed> */
    public function collect(): array
    {
        $periods = DB::table('periods')->where('name', 'not like', '%Contoh%')->get();
        $periodIds = $periods->pluck('id')->all();
        $divisions = DB::table('divisions')->get();
        $divisionSlug = $divisions->pluck('slug', 'id');
        $periodName = $periods->pluck('name', 'id');
        $demoUserIds = DB::table('users')->where('email', 'like', self::DEMO_EMAIL)->pluck('id')->all();

        $settings = DB::table('settings')->whereIn('key', self::WEBSITE_SETTINGS)->get()
            ->map(function ($row) {
                $value = json_decode((string) $row->value, true);

                if ($row->key === 'hero_image' && filled($value)) {
                    $this->file('public', $value);
                }

                return ['key' => $row->key, 'value' => $value];
            })->values()->all();

        $structure = DB::table('structure_members')->whereIn('period_id', $periodIds ?: [0])
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereNotIn('user_id', $demoUserIds ?: [0]))
            ->orderBy('sort_order')->get()
            ->map(fn ($r) => [
                'period' => $periodName[$r->period_id] ?? null,
                'division' => $divisionSlug[$r->division_id] ?? null,
                'name' => $r->name, 'position' => $r->position, 'prodi' => $r->prodi,
                'photo' => $this->file('public', $r->photo), 'sort_order' => $r->sort_order,
            ])->filter(fn ($r) => $r['period'] !== null)->values()->all();

        $programs = DB::table('programs')->whereIn('period_id', $periodIds ?: [0])->get()
            ->map(fn ($r) => [
                'period' => $periodName[$r->period_id] ?? null,
                'division' => $divisionSlug[$r->division_id] ?? null,
                'title' => $r->title, 'description' => $r->description, 'status' => $r->status,
                'starts_on' => $r->starts_on, 'ends_on' => $r->ends_on,
            ])->values()->all();

        // Agenda: tanpa demo ("Contoh:") dan tanpa tugas konten (terikat akun penanggung jawab).
        $events = DB::table('events')->where('title', 'not like', 'Contoh:%')->where('category', '!=', 'content')->get()
            ->map(fn ($r) => [
                'title' => $r->title, 'description' => $r->description, 'category' => $r->category,
                'visibility' => $r->visibility, 'division' => $divisionSlug[$r->division_id] ?? null,
                'starts_at' => $r->starts_at, 'ends_at' => $r->ends_at, 'all_day' => (bool) $r->all_day,
                'location' => $r->location, 'organizer' => $r->organizer, 'registration_url' => $r->registration_url,
                'has_conflict' => (bool) $r->has_conflict,
            ])->values()->all();

        // Organisasi: tanpa organisasi yang HANYA punya akun demo.
        $organizations = DB::table('organizations')->get()
            ->reject(function ($org) {
                $emails = DB::table('users')->where('organization_id', $org->id)->pluck('email');

                return $emails->isNotEmpty() && $emails->every(fn ($e) => str_ends_with((string) $e, '@demo.test'));
            })
            ->map(fn ($r) => ['name' => $r->name, 'type' => $r->type, 'is_active' => (bool) $r->is_active])
            ->values()->all();

        $templates = DB::table('document_templates')->get()
            ->map(fn ($r) => ['name' => $r->name, 'content' => $r->content, 'has_letterhead' => (bool) $r->has_letterhead])
            ->values()->all();

        // Berita/kajian/press release TERBIT, bukan contoh-*.
        $posts = DB::table('posts')->whereNull('deleted_at')->where('slug', 'not like', 'contoh-%')
            ->where('status', PostStatus::Published->value)->get()
            ->map(fn ($r) => [
                'type' => $r->type, 'title' => $r->title, 'slug' => $r->slug, 'excerpt' => $r->excerpt, 'body' => $r->body,
                'cover' => $this->file('public', $r->cover),
                'cover_original_path' => $this->file('local', $r->cover_original_path), 'cover_original_name' => $r->cover_original_name,
                'social_caption' => $r->social_caption, 'category' => $r->category,
                'attachment_path' => $this->file('public', $r->attachment_path), 'attachment_name' => $r->attachment_name,
                'status' => $r->status, 'published_at' => $r->published_at,
                'social_posted_at' => $r->social_posted_at, 'social_post_url' => $r->social_post_url,
            ])->values()->all();

        $galleries = DB::table('galleries')->where('slug', 'not like', 'contoh-%')->get()
            ->map(fn ($g) => [
                'title' => $g->title, 'slug' => $g->slug, 'event_date' => $g->event_date, 'description' => $g->description,
                'photos' => DB::table('gallery_photos')->where('gallery_id', $g->id)->orderBy('sort_order')->get()
                    ->map(fn ($p) => [
                        'path' => $this->file('public', $p->path), 'thumb_path' => $this->file('public', $p->thumb_path),
                        'caption' => $p->caption, 'sort_order' => $p->sort_order,
                    ])->values()->all(),
            ])->values()->all();

        $downloads = DB::table('downloads')->where(fn ($q) => $q->whereNull('description')->orWhere('description', 'not like', 'Contoh%'))->get()
            ->map(fn ($r) => [
                'title' => $r->title, 'category' => $r->category, 'description' => $r->description,
                'file_path' => $this->file('local', $r->file_path), 'original_name' => $r->original_name, 'mime_type' => $r->mime_type,
                'size' => $r->size, 'is_active' => (bool) $r->is_active,
            ])->values()->all();

        return [
            'format' => self::FORMAT,
            'exported_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'tables' => [
                'settings' => $settings,
                'divisions' => $divisions->map(fn ($r) => ['name' => $r->name, 'slug' => $r->slug, 'color' => $r->color, 'description' => $r->description])->values()->all(),
                'periods' => $periods->map(fn ($r) => ['name' => $r->name, 'starts_on' => $r->starts_on, 'ends_on' => $r->ends_on, 'is_active' => (bool) $r->is_active])->values()->all(),
                'structure_members' => $structure,
                'programs' => $programs,
                'events' => $events,
                'organizations' => $organizations,
                'document_templates' => $templates,
                'posts' => $posts,
                'galleries' => $galleries,
                'downloads' => $downloads,
            ],
        ];
    }

    private function file(string $disk, ?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $this->files[$disk.':'.$path] = ['disk' => $disk, 'path' => $path];

        return $path;
    }

    // =================================================================== IMPORT

    /**
     * @return array<string, array{create: int, update: int}>
     */
    public function import(string $zipPath, bool $dryRun = false): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Berkas paket tidak bisa dibuka.');
        }

        $data = json_decode((string) $zip->getFromName('data.json'), true);

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            $zip->close();

            throw new RuntimeException('Format paket tidak dikenali (bukan hasil bem:export-data).');
        }

        $report = [];
        $t = $data['tables'];

        DB::beginTransaction();

        try {
            $report['settings'] = $this->importSettings($t['settings'] ?? []);
            $report['divisions'] = $this->upsertMany('divisions', $t['divisions'] ?? [], fn ($r) => ['slug' => $r['slug']]);
            $report['periods'] = $this->importPeriods($t['periods'] ?? []);

            $division = DB::table('divisions')->pluck('id', 'slug');
            $period = DB::table('periods')->pluck('id', 'name');

            $report['structure_members'] = $this->upsertMany('structure_members', array_map(fn ($r) => [
                'period_id' => $period[$r['period']] ?? null, 'division_id' => $division[$r['division']] ?? null,
                'user_id' => null, 'name' => $r['name'], 'position' => $r['position'], 'prodi' => $r['prodi'],
                'photo' => $r['photo'], 'sort_order' => $r['sort_order'],
            ], $t['structure_members'] ?? []), fn ($r) => ['period_id' => $r['period_id'], 'name' => $r['name'], 'position' => $r['position']]);

            $report['programs'] = $this->upsertMany('programs', array_map(fn ($r) => [
                'period_id' => $period[$r['period']] ?? null, 'division_id' => $division[$r['division']] ?? null,
                'title' => $r['title'], 'description' => $r['description'], 'status' => $r['status'],
                'starts_on' => $r['starts_on'], 'ends_on' => $r['ends_on'],
            ], $t['programs'] ?? []), fn ($r) => ['period_id' => $r['period_id'], 'division_id' => $r['division_id'], 'title' => $r['title']]);

            $report['events'] = $this->importEvents($t['events'] ?? [], $division->all());
            $report['organizations'] = $this->upsertMany('organizations', $t['organizations'] ?? [], fn ($r) => ['name' => $r['name']]);
            $report['document_templates'] = $this->upsertMany('document_templates', array_map(fn ($r) => [...$r, 'created_by' => null], $t['document_templates'] ?? []), fn ($r) => ['name' => $r['name']]);

            // Berita impor dicatat atas nama Super Admin aktif pertama (akun lokal tidak ikut dipindah).
            $author = $this->importAuthorId($t['posts'] ?? []);
            $report['posts'] = $this->upsertMany('posts', array_map(fn ($r) => [
                ...$r, 'author_id' => $author, 'published_by' => $author, 'event_assignee_id' => null, 'review_round' => 0, 'deleted_at' => null,
            ], $t['posts'] ?? []), fn ($r) => ['slug' => $r['slug']], ignoreOnUpdate: ['author_id', 'published_by']);

            $report['galleries'] = $this->importGalleries($t['galleries'] ?? []);
            $report['downloads'] = $this->upsertMany('downloads', array_map(fn ($r) => [...$r, 'download_count' => 0], $t['downloads'] ?? []),
                fn ($r) => ['title' => $r['title'], 'category' => $r['category']], ignoreOnUpdate: ['download_count']);

            $report['files'] = $dryRun ? ['create' => $this->countNewFiles($zip), 'update' => 0] : $this->extractFiles($zip);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $zip->close();

            throw $e;
        }

        $zip->close();

        return $report;
    }

    private function importAuthorId(array $posts): ?int
    {
        if ($posts === []) {
            return null;
        }

        $id = User::query()->activeSuperAdmins()->orderBy('id')->value('id');

        if ($id === null) {
            throw new RuntimeException('Belum ada akun Super Admin aktif. Jalankan dulu: php artisan bem:create-admin');
        }

        return (int) $id;
    }

    /** @return array{create: int, update: int} */
    private function importSettings(array $rows): array
    {
        $result = ['create' => 0, 'update' => 0];

        foreach ($rows as $row) {
            if (! in_array($row['key'], self::WEBSITE_SETTINGS, true)) {
                continue; // setting teknis tidak pernah diimpor
            }

            $exists = DB::table('settings')->where('key', $row['key'])->exists();
            DB::table('settings')->updateOrInsert(['key' => $row['key']], ['value' => json_encode($row['value']), 'updated_at' => now(), ...($exists ? [] : ['created_at' => now()])]);
            $result[$exists ? 'update' : 'create']++;
        }

        Setting::flushCache();

        return $result;
    }

    /** @return array{create: int, update: int} */
    private function importPeriods(array $rows): array
    {
        $result = $this->upsertMany('periods', $rows, fn ($r) => ['name' => $r['name']]);
        $active = collect($rows)->firstWhere('is_active', true);

        if ($active) {
            DB::table('periods')->where('name', '!=', $active['name'])->update(['is_active' => false]);
        }

        return $result;
    }

    /** @param array<string, int> $division @return array{create: int, update: int} */
    private function importEvents(array $rows, array $division): array
    {
        $result = ['create' => 0, 'update' => 0];

        foreach ($rows as $r) {
            $values = [...$r, 'division_id' => $r['division'] ? ($division[$r['division']] ?? null) : null, 'created_by' => null];
            unset($values['division']);

            $existing = DB::table('events')->where('title', $r['title'])->where('category', $r['category'])
                ->whereDate('starts_at', Carbon::parse($r['starts_at'])->toDateString())->first();

            if ($existing) {
                DB::table('events')->where('id', $existing->id)->update([...$values, 'updated_at' => now()]);
                $result['update']++;
            } else {
                DB::table('events')->insert([...$values, 'created_at' => now(), 'updated_at' => now()]);
                $result['create']++;
            }
        }

        return $result;
    }

    /** @return array{create: int, update: int} */
    private function importGalleries(array $rows): array
    {
        $result = ['create' => 0, 'update' => 0];

        foreach ($rows as $g) {
            $photos = $g['photos'] ?? [];
            unset($g['photos']);
            $r = $this->upsertMany('galleries', [$g], fn ($x) => ['slug' => $x['slug']]);
            $result['create'] += $r['create'];
            $result['update'] += $r['update'];

            $galleryId = DB::table('galleries')->where('slug', $g['slug'])->value('id');

            $this->upsertMany('gallery_photos', array_map(fn ($p) => [...$p, 'gallery_id' => $galleryId], $photos),
                fn ($p) => ['gallery_id' => $p['gallery_id'], 'path' => $p['path']]);
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $key
     * @param  list<string>  $ignoreOnUpdate
     * @return array{create: int, update: int}
     */
    private function upsertMany(string $table, array $rows, callable $key, array $ignoreOnUpdate = []): array
    {
        $result = ['create' => 0, 'update' => 0];
        $columns = array_flip(DB::getSchemaBuilder()->getColumnListing($table));

        foreach ($rows as $row) {
            $row = array_intersect_key($row, $columns);
            $match = $key($row);

            if (in_array(null, $match, true) && in_array($table, ['structure_members', 'programs'], true) && ($match['period_id'] ?? null) === null) {
                continue; // periode tidak ditemukan
            }

            $existing = DB::table($table)->where($match)->first();

            if ($existing) {
                DB::table($table)->where('id', $existing->id)->update([...array_diff_key($row, array_flip($ignoreOnUpdate)), 'updated_at' => now()]);
                $result['update']++;
            } else {
                DB::table($table)->insert([...$row, 'created_at' => now(), 'updated_at' => now()]);
                $result['create']++;
            }
        }

        return $result;
    }

    /** @return array{create: int, update: int} */
    private function extractFiles(ZipArchive $zip): array
    {
        $result = ['create' => 0, 'update' => 0];

        foreach ($this->fileEntries($zip) as [$index, $disk, $path]) {
            $storage = Storage::disk($disk);

            if ($storage->exists($path)) {
                continue; // nama UUID: berkas sama sudah ada
            }

            $storage->writeStream($path, $zip->getStream($zip->getNameIndex($index)));
            $result['create']++;
        }

        return $result;
    }

    private function countNewFiles(ZipArchive $zip): int
    {
        return collect($this->fileEntries($zip))->reject(fn ($e) => Storage::disk($e[1])->exists($e[2]))->count();
    }

    /** @return Collection<int, array{0: int, 1: string, 2: string}> hanya disk public/local, tanpa path traversal */
    private function fileEntries(ZipArchive $zip): Collection
    {
        $entries = collect();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (! preg_match('#^files/(public|local)/(.+)$#', $name, $m) || str_contains($m[2], '..') || str_ends_with($name, '/')) {
                continue;
            }

            $entries->push([$i, $m[1], $m[2]]);
        }

        return $entries;
    }
}
