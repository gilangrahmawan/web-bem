<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Membuat akun Super Admin pertama (Ketua) dari .env.
 * Menolak berjalan selama nilai .env masih placeholder (GANTI_...).
 * Jika email sudah ada, password TIDAK ditimpa.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('bem.superadmin');
        $marker = (string) config('bem.placeholder_marker', 'GANTI');

        foreach (['name', 'email', 'password'] as $field) {
            $value = (string) ($config[$field] ?? '');

            if ($value === '' || str_contains(strtoupper($value), $marker)) {
                throw new RuntimeException(
                    'SuperAdminSeeder dihentikan: isi SUPERADMIN_NAME, SUPERADMIN_EMAIL, dan SUPERADMIN_PASSWORD '
                    .'di file .env (nilai GANTI_... masih placeholder), lalu jalankan ulang.'
                );
            }
        }

        $validator = Validator::make($config, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', Password::default()],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('SuperAdminSeeder: data .env tidak valid — '.implode(' ', $validator->errors()->all()));
        }

        $user = User::query()->where('email', $config['email'])->first();

        if (! $user) {
            $user = new User;
            $user->fill([
                'name' => $config['name'],
                'email' => $config['email'],
                'position' => 'Ketua Umum',
                'division_id' => Division::query()->where('slug', 'bph')->value('id'),
            ]);
            $user->forceFill([
                'password' => $config['password'],
                'is_active' => true,
                // Password berasal dari .env (teks biasa), jadi wajib diganti saat login pertama.
                'must_change_password' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->command?->info("Super Admin dibuat: {$user->email}");
        } else {
            $this->command?->warn("Super Admin {$user->email} sudah ada; password tidak diubah.");
        }

        $user->assignRole(RoleName::SuperAdmin->value);
    }
}
