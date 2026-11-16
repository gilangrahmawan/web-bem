<?php

namespace Database\Seeders;

use App\Enums\ApplicantStatus;
use App\Models\Division;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Support\TrackingCode;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Gelombang oprec contoh + beberapa pendaftar di berbagai status (HANYA lokal).
 * Tanpa berkas CV/foto. Hanya menambah; dilewati bila gelombang contoh sudah ada.
 */
class DemoRecruitmentSeeder extends Seeder
{
    public const NAME = 'Oprec Contoh — Gelombang 1';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoRecruitmentSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        if (Recruitment::query()->where('name', self::NAME)->exists()) {
            $this->command?->warn('Oprec demo sudah ada; dilewati.');

            return;
        }

        $d = Division::query()->pluck('id', 'slug');

        $wave = new Recruitment([
            'name' => self::NAME,
            'description' => 'Contoh gelombang untuk mencoba alur oprec di lokal.',
            'opens_at' => now()->subDays(3)->startOfDay(),
            'closes_at' => now()->addDays(14)->endOfDay(),
            'open_divisions' => [$d['kajian'], $d['medpub'], $d['humas']],
            'is_active' => true,
        ]);
        $wave->save();

        $rows = [
            ['Aulia Rahma', '2025110001', 'kajian', 'humas', ApplicantStatus::Registered, 3],
            ['Bagas Saputra', '2025110002', 'medpub', null, ApplicantStatus::Registered, 2],
            ['Citra Lestari', '2024110003', 'humas', 'kajian', ApplicantStatus::DocumentPassed, 2],
            ['Dewa Mahendra', '2024110004', 'kajian', 'medpub', ApplicantStatus::Interview, 1],
            ['Eka Wulandari', '2025110005', 'medpub', 'humas', ApplicantStatus::Interview, 1],
            ['Fajar Nugroho', '2025110006', 'humas', null, ApplicantStatus::Registered, 0],
        ];

        foreach ($rows as [$name, $nim, $first, $second, $status, $daysAgo]) {
            $applicant = new RecruitmentApplicant([
                'registration_code' => TrackingCode::generate('REG', fn (string $c) => RecruitmentApplicant::query()->where('registration_code', $c)->exists()),
                'name' => $name,
                'nim' => $nim,
                'prodi' => 'Informatika',
                'batch_year' => (int) substr($nim, 0, 4),
                'phone' => '0812000'.substr($nim, -5),
                'email' => strtolower(str_replace(' ', '.', $name)).'@contoh.test',
                'first_choice_division_id' => $d[$first],
                'second_choice_division_id' => $second ? $d[$second] : null,
                'motivation' => 'Ingin belajar berorganisasi dan berkontribusi untuk mahasiswa UBHINUS.',
                'consent_at' => now()->subDays($daysAgo),
            ]);
            $applicant->recruitment_id = $wave->id;
            $applicant->forceFill([
                'status' => $status,
                'interview_at' => $status === ApplicantStatus::Interview ? now()->addDays(2)->setTime(13, 0) : null,
                'interview_location' => $status === ApplicantStatus::Interview ? 'Sekretariat BEM' : null,
                'created_at' => now()->subDays($daysAgo),
            ])->save();

            $applicant->logs()->create(['to_status' => ApplicantStatus::Registered, 'note' => 'Data demo.']);
        }

        $this->command?->info('Oprec demo dibuat: 1 gelombang terbuka + '.count($rows).' pendaftar.');
    }
}
