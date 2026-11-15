<?php

namespace App\Filament\Auth;

use App\Services\ActivityLogger;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login panel dengan aturan AGENTS.md B4:
 * - 5 kali gagal (per email + IP) → dikunci 15 menit.
 * - User nonaktif ditolak (lewat User::canAccessPanel, pesan generik).
 * - Tanpa "ingat saya", agar batas session 30 menit tetap berlaku.
 */
class Login extends BaseLogin
{
    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        $key = $this->throttleKey();
        $maxAttempts = (int) config('bem.login.max_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->throwLockedOut($key);
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            // Hanya gagal kredensial/akses yang dihitung, bukan error format (mis. email kosong).
            if (array_key_exists('data.email', $exception->errors())
                && filled($this->data['email'] ?? null)
                && filled($this->data['password'] ?? null)) {
                RateLimiter::hit($key, (int) config('bem.login.lockout_seconds', 900));

                if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                    app(ActivityLogger::class)->log('auth.lockout', null, [
                        'email' => mb_substr((string) $this->data['email'], 0, 191),
                    ]);
                }
            }

            throw $exception;
        }

        if ($response !== null) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
            ]);
    }

    protected function throttleKey(): string
    {
        $email = Str::lower(trim((string) ($this->data['email'] ?? '')));

        return 'panel-login:'.sha1($email.'|'.request()->ip());
    }

    protected function throwLockedOut(string $key): never
    {
        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

        throw ValidationException::withMessages([
            'data.email' => "Terlalu banyak percobaan login gagal. Akun dikunci sementara, coba lagi dalam {$minutes} menit.",
        ]);
    }
}
