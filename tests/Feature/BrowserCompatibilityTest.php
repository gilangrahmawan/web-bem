<?php

use App\Enums\DriveFileKind;
use App\Enums\RoleName;
use App\Models\DriveFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/** Ambil satu direktif CSP, mis. "worker-src". */
function cspDirective(string $csp, string $name): string
{
    foreach (array_map('trim', explode(';', $csp)) as $directive) {
        if (str_starts_with($directive, $name.' ')) {
            return $directive;
        }
    }

    return '';
}

it('CSP mengizinkan worker blob (resize gambar FilePond) tanpa melonggarkan script-src', function (): void {
    $csp = (string) get('/')->headers->get('Content-Security-Policy');

    expect(cspDirective($csp, 'worker-src'))->toBe("worker-src 'self' blob:")
        ->and(cspDirective($csp, 'script-src'))->not->toContain('blob:')->not->toContain('data:')
        ->and(cspDirective($csp, 'img-src'))->toContain('blob:')->toContain('data:')
        ->and(cspDirective($csp, 'frame-src'))->toContain("'self'")
        ->and(cspDirective($csp, 'object-src'))->toBe("object-src 'none'");

    // Header yang sama berlaku di panel.
    actingAs(member(RoleName::AnggotaKajian));
    expect(cspDirective((string) get('/panel-bem')->headers->get('Content-Security-Policy'), 'worker-src'))->toBe("worker-src 'self' blob:");
});

it('favicon.ico tersedia (ICO dari logo BEM) dan dipasang di halaman publik & panel', function (): void {
    $ico = file_get_contents(public_path('favicon.ico'));
    expect(substr($ico, 0, 4))->toBe("\x00\x00\x01\x00")              // header ICO
        ->and(unpack('v', substr($ico, 4, 2))[1])->toBe(3);         // 16, 32, 48 px

    get('/')->assertSee('favicon.ico', false);

    actingAs(member(RoleName::Sekretaris));
    get('/panel-bem')->assertSee('favicon.ico', false);
});

it('manifest PWA: start_url berada di dalam scope (sama persis, tanpa garis miring yang dialihkan .htaccess)', function (): void {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['start_url'])->toBe('/panel-bem')
        ->and($manifest['scope'])->toBe('/panel-bem')
        ->and(str_starts_with($manifest['start_url'], $manifest['scope']))->toBeTrue();

    get('/panel-bem')->assertRedirect(); // tamu → login, bukan 404
});

it('pratinjau Drive: PDF tanpa sandbox (viewer PDF browser), gambar tetap sandbox', function (): void {
    Storage::fake('local');
    actingAs(member(RoleName::Sekretaris));

    $pdf = DriveFile::factory()->create();
    $image = DriveFile::factory()->create([
        'original_name' => 'foto.png', 'stored_name' => Str::uuid().'.png', 'mime_type' => 'image/png',
        'extension' => 'png', 'kind' => DriveFileKind::Image,
    ]);

    $pdfCsp = (string) get(route('panel.drive.files.preview', $pdf))->assertOk()->headers->get('Content-Security-Policy');
    $imageCsp = (string) get(route('panel.drive.files.preview', $image))->assertOk()->headers->get('Content-Security-Policy');

    expect($pdfCsp)->not->toContain('sandbox')->toContain("default-src 'none'")->toContain("frame-ancestors 'self'")
        ->and($imageCsp)->toStartWith('sandbox;');
});
