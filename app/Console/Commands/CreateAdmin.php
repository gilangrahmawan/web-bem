<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\Division;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Membuat akun Super Admin (Ketua/Wakil) secara interaktif — untuk produksi (Fase 10).
 * Password diketik tersembunyi dan TIDAK PERNAH ditulis ke .env atau log.
 * Akun wajib ganti password saat login pertama, lalu wajib mengatur 2FA.
 */
#[Signature('bem:create-admin {--name= : Nama lengkap} {--email= : Email login} {--position=Ketua Umum : Jabatan}')]
#[Description('Buat akun Super Admin (Ketua/Wakil) secara interaktif tanpa menyimpan password di .env')]
class CreateAdmin extends Command
{
    public function handle(ActivityLogger $logger): int
    {
        if (! Role::query()->where('name', RoleName::SuperAdmin->value)->exists()) {
            $this->error('Role super_admin belum ada. Jalankan dulu: php artisan db:seed --force (tanpa DemoSeeder).');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Nama lengkap');
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email login'))));
        $password = (string) $this->secret('Password sementara (min. 10 karakter, huruf, angka, simbol; tidak ditampilkan)');
        $confirm = (string) $this->secret('Ulangi password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:191', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::default()],
            ],
            ['email.unique' => 'Email sudah dipakai akun lain.', 'password.confirmed' => 'Konfirmasi password tidak sama.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password, $logger): User {
            $user = new User([
                'name' => trim((string) $name),
                'email' => $email,
                'position' => (string) $this->option('position'),
                'division_id' => Division::query()->where('slug', 'bph')->value('id'),
            ]);
            $user->forceFill([
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ])->save();
            $user->assignRole(RoleName::SuperAdmin->value);

            $logger->log('user.created_via_console', $user, ['role' => RoleName::SuperAdmin->value], null, recordIp: false);

            return $user;
        });

        $this->info("Super Admin dibuat: {$user->email}");
        $this->line('Saat login pertama: wajib ganti password, lalu wajib mengatur 2FA (aplikasi authenticator).');

        return self::SUCCESS;
    }
}
