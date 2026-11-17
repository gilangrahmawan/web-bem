<?php

use App\Http\Middleware\ConfigureRequestUrls;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // POST /broadcasting/auth (middleware web: sesi + CSRF); aturan per channel di routes/channels.php.
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        // URL absolut: lokal mengikuti host yang diakses (uji dari HP), produksi dikunci ke APP_URL + HTTPS.
        $middleware->prepend(ConfigureRequestUrls::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
