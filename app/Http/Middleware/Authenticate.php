<?php

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Autentikasi panel. Tambahan: user yang dinonaktifkan saat sedang login
 * langsung dikeluarkan (session dihapus) lalu diarahkan ke halaman login,
 * bukan sekadar mendapat 403.
 */
class Authenticate extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();
        $user = $guard->user();

        if ($user instanceof User && ! $user->is_active) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->unauthenticated($request, $guards);
        }

        parent::authenticate($request, $guards);
    }
}
