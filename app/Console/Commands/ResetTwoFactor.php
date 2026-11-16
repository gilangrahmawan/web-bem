<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Darurat: reset 2FA lewat server (mis. semua Super Admin kehilangan HP & kode pemulihan).
 * Normalnya reset dilakukan Super Admin lain dari menu User → Reset 2FA.
 */
#[Signature('bem:reset-2fa {email : Email akun}')]
#[Description('Reset 2FA satu akun (darurat, lewat server)')]
class ResetTwoFactor extends Command
{
    public function handle(UserAccountService $accounts): int
    {
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();

        if (! $user) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset 2FA untuk {$user->name} ({$user->email})? Semua session akun ini diakhiri.", true)) {
            return self::FAILURE;
        }

        $accounts->resetTwoFactor(null, $user);
        $this->info('2FA direset. Saat login berikutnya, akun diminta mengatur ulang 2FA (bila jabatannya wajib).');

        return self::SUCCESS;
    }
}
