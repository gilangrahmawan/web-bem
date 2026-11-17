<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * URL absolut per environment.
 *
 * - LOKAL: semua URL mengikuti host & port yang sedang diakses (mis. http://192.168.1.5:8000 dari HP),
 *   bukan APP_URL — termasuk URL berkas di disk `public` (foto, cover, galeri) yang secara bawaan memakai
 *   APP_URL. Berkas `public/hot` (npm run dev) diabaikan bila host-nya berbeda dari host yang diakses,
 *   sehingga CSS/JS memakai hasil `npm run build` yang bisa dimuat dari perangkat lain.
 *   TIDAK memakai URL::forceRootUrl.
 * - PRODUKSI: semua URL dikunci ke APP_URL (+ HTTPS bila APP_URL https), apa pun Host header yang
 *   dikirim klien — mencegah tautan/aset mengarah ke host palsu.
 */
class ConfigureRequestUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            $root = rtrim((string) config('app.url'), '/');

            if ($root !== '') {
                URL::forceRootUrl($root);

                if (str_starts_with($root, 'https://')) {
                    URL::forceScheme('https');
                }
            }

            return $next($request);
        }

        if (app()->isLocal()) {
            $this->followRequestHost($request);
        }

        return $next($request);
    }

    private function followRequestHost(Request $request): void
    {
        $origin = $request->getSchemeAndHttpHost();

        // Disk public: URL berkas mengikuti host yang diakses.
        config(['filesystems.disks.public.url' => $origin.'/storage']);
        Storage::forgetDisk('public');

        // npm run dev: hot file berisi alamat server Vite (mis. http://bemweb.test:5173) yang belum tentu
        // bisa dijangkau perangkat lain → pakai hasil build bila host berbeda.
        $hot = public_path('hot');

        $reachable = ! is_file($hot) || $this->sameMachine(parse_url(trim((string) file_get_contents($hot)), PHP_URL_HOST), $request->getHost());

        // Selalu diset per request (aman bila instance aplikasi dipakai ulang antar-request).
        Vite::useHotFile($reachable ? $hot : storage_path('framework/vite.hot.ignored'));
    }

    /** Server Vite dev bisa dijangkau bila host-nya sama, atau keduanya alamat loopback laptop ini. */
    private function sameMachine(?string $hotHost, string $requestHost): bool
    {
        $loopback = ['localhost', '127.0.0.1', '::1', '[::1]'];

        return $hotHost !== null
            && ($hotHost === $requestHost || (in_array($hotHost, $loopback, true) && in_array($requestHost, $loopback, true)));
    }
}
