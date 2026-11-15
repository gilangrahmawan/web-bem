<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verifikasi token Cloudflare Turnstile (AGENTS.md B4: form publik).
 * Fail-closed: token kosong, kunci belum diisi, atau Cloudflare tak terjangkau → DITOLAK.
 * Test memakai Http::fake(), tidak pernah memanggil server Cloudflare.
 */
class TurnstileVerifier
{
    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key') ?: null;
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret_key');

        if (blank($token) || blank($secret)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            Log::warning('Turnstile tidak bisa dihubungi: '.$e->getMessage());

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
