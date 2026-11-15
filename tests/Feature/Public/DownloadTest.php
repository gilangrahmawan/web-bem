<?php

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

beforeEach(function (): void {
    Storage::fake(Download::DISK);
    Storage::fake('public');
});

function storedDownload(array $attributes = []): Download
{
    $download = Download::factory()->create(array_merge(['original_name' => 'template-proposal.pdf'], $attributes));
    Storage::disk(Download::DISK)->put($download->file_path, '%PDF-1.4 contoh');

    return $download;
}

it('mengunduh berkas aktif dengan nama asli dan menambah hitungan', function (): void {
    $download = storedDownload();

    get(route('public.downloads.file', $download))
        ->assertOk()
        ->assertDownload('template-proposal.pdf');

    expect($download->fresh()->download_count)->toBe(1);
});

it('berkas nonaktif menghasilkan 404 dan tidak menambah hitungan', function (): void {
    $download = storedDownload(['is_active' => false]);

    get(route('public.downloads.file', $download))->assertNotFound();

    expect($download->fresh()->download_count)->toBe(0);
});

it('berkas nonaktif tidak tampil di daftar unduhan', function (): void {
    storedDownload(['title' => 'Dokumen Aktif']);
    storedDownload(['title' => 'Dokumen Tersembunyi', 'is_active' => false]);

    get('/unduhan')->assertOk()->assertSee('Dokumen Aktif')->assertDontSee('Dokumen Tersembunyi');
});

it('berkas yang hilang dari disk menghasilkan 404', function (): void {
    $download = Download::factory()->create();

    get(route('public.downloads.file', $download))->assertNotFound();
});

it('berkas unduhan disimpan privat, tidak bisa diakses lewat /storage', function (): void {
    $download = storedDownload();

    expect(Storage::disk('public')->exists($download->file_path))->toBeFalse();

    // Disk privat hanya bisa diakses lewat URL bertanda tangan → tanpa tanda tangan ditolak.
    expect(get('/storage/'.$download->file_path)->status())->toBeIn([403, 404]);
});

it('menghapus data unduhan ikut menghapus berkasnya', function (): void {
    $download = storedDownload();

    $download->delete();

    Storage::disk(Download::DISK)->assertMissing($download->file_path);
});
