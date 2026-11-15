<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User dengan must_change_password=true (login pertama / setelah reset)
 * hanya boleh membuka halaman Ganti Password dan logout.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        $panelId = Filament::getCurrentOrDefaultPanel()->getId();

        if ($request->routeIs(
            ChangePassword::getRouteName(),
            "filament.{$panelId}.auth.logout",
        )) {
            return $next($request);
        }

        // Request Livewire (JSON) tidak bisa di-redirect dengan aman.
        abort_if($request->hasHeader('X-Livewire'), 403, 'Ganti password terlebih dahulu.');

        return redirect()->to(ChangePassword::getUrl());
    }
}
