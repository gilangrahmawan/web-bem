<?php

use App\Enums\AspirationStatus;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Aspiration;
use App\Services\AspirationService;
use App\Support\TrackingCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function aspirationPayload(array $overrides = []): array
{
    return [
        'name' => 'Rina Kartika',
        'contact' => 'rina@contoh.test',
        'category' => 'facility',
        'body' => 'Lampu di parkiran belakang gedung B mati sejak dua minggu lalu, rawan saat malam.',
        'cf-turnstile-response' => 'token-dari-widget',
        ...$overrides,
    ];
}

it('form aspirasi tampil dengan widget Turnstile', function (): void {
    fakeTurnstile();

    get('/aspirasi')->assertOk()->assertSee('Kirim Aspirasi')->assertSee('cf-turnstile', false)->assertSee('1x00000000000000000000AA');
});

it('aspirasi terkirim dengan kode tracking acak tanpa karakter ambigu', function (): void {
    fakeTurnstile();

    post('/aspirasi', aspirationPayload())->assertRedirect('/aspirasi')->assertSessionHas('submitted_code');

    $aspiration = Aspiration::sole();
    expect($aspiration->tracking_code)->toMatch('/^ASP-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
        ->and($aspiration->tracking_code)->not->toMatch('/[01OI]/')
        ->and($aspiration->status)->toBe(AspirationStatus::New)
        ->and($aspiration->name)->toBe('Rina Kartika');

    get('/aspirasi')->assertSee($aspiration->tracking_code);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'challenges.cloudflare.com'));
});

it('aspirasi anonim tidak menyimpan nama, kontak, maupun IP', function (): void {
    fakeTurnstile();

    post('/aspirasi', aspirationPayload(['is_anonymous' => '1']))->assertRedirect();

    $aspiration = Aspiration::sole();
    expect($aspiration->is_anonymous)->toBeTrue()
        ->and($aspiration->name)->toBeNull()
        ->and($aspiration->contact)->toBeNull()
        ->and(ActivityLog::where('action', 'aspiration.submitted')->sole()->ip)->toBeNull();
});

it('Turnstile gagal, kosong, atau belum dikonfigurasi → aspirasi ditolak', function (): void {
    fakeTurnstile(success: false);
    post('/aspirasi', aspirationPayload())->assertSessionHasErrors('cf-turnstile-response');

    fakeTurnstile();
    post('/aspirasi', aspirationPayload(['cf-turnstile-response' => '']))->assertSessionHasErrors('cf-turnstile-response');

    config(['services.turnstile.secret_key' => null]);
    post('/aspirasi', aspirationPayload())->assertSessionHasErrors('cf-turnstile-response');

    expect(Aspiration::count())->toBe(0);
});

it('Cloudflare tidak bisa dihubungi → ditolak (fail-closed)', function (): void {
    fakeTurnstile();
    Http::fake(['challenges.cloudflare.com/*' => fn () => throw new ConnectionException('timeout')]);

    post('/aspirasi', aspirationPayload())->assertSessionHasErrors('cf-turnstile-response');
    expect(Aspiration::count())->toBe(0);
});

it('validasi isi dan kategori aspirasi', function (): void {
    fakeTurnstile();

    post('/aspirasi', aspirationPayload(['body' => 'terlalu pendek', 'category' => 'ngawur']))
        ->assertSessionHasErrors(['body', 'category']);
});

it('kirim aspirasi dibatasi 5 per IP per jam', function (): void {
    fakeTurnstile();

    foreach (range(1, 5) as $i) {
        post('/aspirasi', aspirationPayload())->assertRedirect();
    }

    post('/aspirasi', aspirationPayload())->assertStatus(429);
    expect(Aspiration::count())->toBe(5);
});

it('cek status dibatasi 20 per IP per jam', function (): void {
    fakeTurnstile();

    foreach (range(1, 20) as $i) {
        post('/aspirasi/status', ['tracking_code' => 'ASP-AAAA-AAAA', 'cf-turnstile-response' => 'x'])->assertRedirect();
    }

    post('/aspirasi/status', ['tracking_code' => 'ASP-AAAA-AAAA', 'cf-turnstile-response' => 'x'])->assertStatus(429);
});

it('cek status menampilkan status & tanggapan, tanpa catatan internal atau nama pengurus', function (): void {
    fakeTurnstile();
    $humas = member(RoleName::KoordinatorHumas, ['name' => 'Petugas Humas Rahasia']);
    $aspiration = Aspiration::factory()->create();
    $service = app(AspirationService::class);

    $service->review($humas, $aspiration, 'CATATAN-INTERNAL-REVIEW');
    $service->resolve($humas, $aspiration, 'Lampu parkiran sudah diganti oleh bagian sarpras.', 'CATATAN-INTERNAL-SELESAI');

    post('/aspirasi/status', ['tracking_code' => strtolower($aspiration->tracking_code), 'cf-turnstile-response' => 'x'])
        ->assertRedirect('/aspirasi/status');

    $this->get('/aspirasi/status')
        ->assertOk()
        ->assertSee('Selesai')
        ->assertSee('Lampu parkiran sudah diganti oleh bagian sarpras.')
        ->assertDontSee('CATATAN-INTERNAL-REVIEW')
        ->assertDontSee('CATATAN-INTERNAL-SELESAI')
        ->assertDontSee('Petugas Humas Rahasia');
});

it('alasan penolakan tampil ke pengirim, catatan internal tetap tidak tampil', function (): void {
    fakeTurnstile();
    $aspiration = Aspiration::factory()->create();
    $reason = 'Mohon maaf, aspirasi ini di luar kewenangan BEM. Silakan sampaikan ke Biro Administrasi Akademik.';

    app(AspirationService::class)->reject(member(RoleName::AnggotaHumas), $aspiration, $reason, 'CATATAN-INTERNAL-PENOLAKAN');

    post('/aspirasi/status', ['tracking_code' => $aspiration->tracking_code, 'cf-turnstile-response' => 'x']);

    $this->get('/aspirasi/status')
        ->assertOk()
        ->assertSee('Tidak dapat ditindaklanjuti')
        ->assertSee($reason)
        ->assertDontSee('CATATAN-INTERNAL-PENOLAKAN');
});

it('kode yang tidak ada atau salah format menghasilkan pesan umum', function (string $code): void {
    fakeTurnstile();

    post('/aspirasi/status', ['tracking_code' => $code, 'cf-turnstile-response' => 'x'])
        ->assertSessionHasErrors('tracking_code');
})->with(['ASP-ZZZZ-ZZZZ', 'bukan-kode', 'PRP-AAAA-AAAA']);

it('generator kode tracking hanya memakai alfabet tanpa karakter ambigu', function (): void {
    foreach (range(1, 200) as $i) {
        $code = TrackingCode::generate('ASP', fn () => false);
        expect(TrackingCode::isValidFormat($code, 'ASP'))->toBeTrue()->and($code)->not->toMatch('/[01OI]/');
    }
});
