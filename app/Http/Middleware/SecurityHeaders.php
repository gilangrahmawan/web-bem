<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers (AGENTS.md B4 → Celah web).
 *
 * CSP dasar: Filament/Livewire/Alpine butuh 'unsafe-inline' dan 'unsafe-eval'.
 * Font Filament dari fonts.bunny.net. Saat lokal, server Vite dev diizinkan.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $vite = app()->isLocal()
            ? ' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173'
            : '';

        return implode('; ', [
            "default-src 'self'",
            // Turnstile (CAPTCHA form publik) memuat skrip & iframe dari challenges.cloudflare.com.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com".$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net".$vite,
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            // Chat real-time: Pusher Channels (WebSocket + fallback HTTP) — Fase 9.
            "connect-src 'self' wss://*.pusher.com https://*.pusher.com".$vite,
            "worker-src 'self'",
            "manifest-src 'self'",
            // 'self': pratinjau PDF Drive (iframe same-origin); peta Google Maps (Kontak); Turnstile.
            "frame-src 'self' https://www.google.com https://challenges.cloudflare.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
