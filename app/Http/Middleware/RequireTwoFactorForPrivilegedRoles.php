<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA wajib HANYA untuk super_admin, sekretaris, bendahara (AGENTS.md Fase 10 + keputusan pemilik).
 * Menggantikan middleware "MFA wajib" bawaan Filament yang berlaku untuk semua user.
 * Urutan: ganti password dulu (EnsurePasswordChanged), baru atur 2FA.
 */
class RequireTwoFactorForPrivilegedRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || $user->must_change_password || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        abort_if($request->hasHeader('X-Livewire'), 403, 'Atur autentikasi dua langkah terlebih dahulu.');

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
