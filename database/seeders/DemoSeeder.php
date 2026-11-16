<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Models\Division;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Akun uji: 1 akun per role, email {role}@demo.test, password dari DEMO_PASSWORD.
 *
 * HANYA UNTUK LOKAL. Seeder ini menolak berjalan jika APP_ENV bukan local.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        $password = (string) config('bem.demo_password');

        if ($password === '') {
            $this->command?->warn('DemoSeeder dilewati: DEMO_PASSWORD kosong di .env.');

            return;
        }

        $divisions = Division::query()->pluck('id', 'slug');

        foreach (RoleName::staffCases() as $role) {
            $email = str_replace('_', '.', $role->value).'@demo.test';

            $user = User::query()->firstOrNew(['email' => $email]);
            $user->fill([
                'name' => 'Demo '.$role->label(),
                'position' => $role->label(),
                'division_id' => $role->divisionSlug() ? $divisions[$role->divisionSlug()] ?? null : null,
            ]);
            $user->forceFill([
                'password' => $password,
                'is_active' => true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ])->save();

            $user->syncRoles([$role->value]);
        }

        // Pengaju kegiatan contoh (bukan pengurus BEM): organisasi "UKM Futsal".
        $futsal = Organization::query()->firstOrCreate(['name' => 'UKM Futsal'], ['type' => OrganizationType::Ukm, 'is_active' => true]);
        $applicant = User::query()->firstOrNew(['email' => 'pengaju.futsal@demo.test']);
        $applicant->fill([
            'name' => 'Demo Pengaju UKM Futsal',
            'position' => 'Pengaju kegiatan · UKM Futsal',
            'division_id' => null,
            'organization_id' => $futsal->id,
        ]);
        $applicant->forceFill([
            'password' => $password,
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ])->save();
        $applicant->syncRoles([RoleName::PengajuKegiatan->value]);

        $this->command?->info('Akun demo dibuat: {role}@demo.test + pengaju.futsal@demo.test (password = DEMO_PASSWORD).');

        $this->call([DemoContentSeeder::class, DemoEventSeeder::class, DemoKasSeeder::class, DemoRecruitmentSeeder::class, DemoDriveSeeder::class]);
    }
}
