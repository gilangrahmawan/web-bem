<?php

namespace App\Rules;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Aturan validasi field `cf-turnstile-response` dari widget Cloudflare Turnstile. */
class ValidTurnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(TurnstileVerifier::class)->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.');
        }
    }
}
