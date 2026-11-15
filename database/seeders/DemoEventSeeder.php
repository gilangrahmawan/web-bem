<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Models\Division;
use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Agenda contoh untuk mencoba kalender: dua kegiatan UKM/BEM yang bentrok, rapat divisi,
 * dan tugas konten Medpub dengan deadline H-2 (untuk mencoba tasks:remind).
 * HANYA UNTUK LOKAL — dipanggil dari DemoSeeder.
 */
class DemoEventSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoEventSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        if (Event::query()->where('title', 'like', 'Contoh:%')->exists()) {
            $this->command?->warn('Agenda demo sudah ada; dilewati.');

            return;
        }

        $actor = User::query()->where('email', 'super.admin@demo.test')->first();

        if ($actor === null) {
            $this->command?->warn('Agenda demo dilewati: akun super.admin@demo.test belum ada.');

            return;
        }

        $divisions = Division::query()->pluck('id', 'slug');
        $assignees = User::query()->whereIn('email', ['anggota.medpub@demo.test', 'koordinator.medpub@demo.test'])->pluck('id')->all();
        $service = app(EventService::class);
        $day = fn (int $offset): string => now()->addDays($offset)->toDateString();

        $events = [
            ['title' => 'Contoh: Dies Natalis UKM Musik', 'category' => EventCategory::Ukm, 'starts_at' => $day(10), 'organizer' => 'UKM Musik', 'location' => 'Aula Kampus'],
            ['title' => 'Contoh: Turnamen Futsal Antar-Prodi', 'category' => EventCategory::Ukm, 'starts_at' => $day(10), 'ends_at' => $day(11), 'organizer' => 'UKM Olahraga'],
            ['title' => 'Contoh: LKMM Tingkat Dasar', 'category' => EventCategory::Bem, 'starts_at' => $day(20), 'ends_at' => $day(21), 'organizer' => 'BEM UBHINUS', 'registration_url' => 'https://example.com/daftar-lkmm'],
            ['title' => 'Contoh: UAS Ganjil', 'category' => EventCategory::Campus, 'starts_at' => $day(30), 'ends_at' => $day(41), 'organizer' => 'BAAK'],
            ['title' => 'Contoh: Rapat Pleno Pengurus', 'category' => EventCategory::Meeting, 'starts_at' => now()->addDays(3)->setTime(19, 0)->toDateTimeString(), 'ends_at' => now()->addDays(3)->setTime(21, 0)->toDateTimeString(), 'all_day' => false, 'location' => 'Sekretariat BEM'],
            ['title' => 'Contoh: Rapat Divisi Kajian', 'category' => EventCategory::Meeting, 'division_id' => $divisions['kajian'] ?? null, 'starts_at' => now()->addDays(5)->setTime(16, 0)->toDateTimeString(), 'all_day' => false],
            ['title' => 'Contoh: Poster Hari Pahlawan', 'category' => EventCategory::Content, 'division_id' => $divisions['medpub'] ?? null, 'starts_at' => $day(2), 'assignee_ids' => $assignees],
        ];

        foreach ($events as $data) {
            if ($data['category'] === EventCategory::Content && $assignees === []) {
                continue;
            }

            $service->create($actor, [...$data, 'category' => $data['category']->value, 'all_day' => $data['all_day'] ?? true]);
        }

        $this->command?->info('Agenda demo dibuat: UKM bentrok, kegiatan BEM, kampus, rapat, dan tugas konten.');
    }
}
