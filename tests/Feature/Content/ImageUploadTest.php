<?php

use App\Enums\RoleName;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Models\Gallery;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(fn () => Storage::fake('public'));

it('mengompres gambar menjadi WebP bernama UUID dan memperkecil ukurannya', function (): void {
    $file = UploadedFile::fake()->image('foto-kegiatan.jpg', 2400, 1600);

    $path = app(ImageService::class)->storeWebp($file, 'tes', 1600);

    expect($path)->toMatch('#^tes/[0-9a-f-]{36}\.webp$#');
    Storage::disk('public')->assertExists($path);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect($width)->toBe(1600)->and($height)->toBe(1067);
});

it('membuat versi besar dan thumbnail', function (): void {
    $paths = app(ImageService::class)->storeWithThumbnail(UploadedFile::fake()->image('a.png', 2000, 1500), 'galeri');

    Storage::disk('public')->assertExists([$paths['path'], $paths['thumb_path']]);
    expect($paths['thumb_path'])->toStartWith('galeri/thumbs/')->toEndWith('.webp');

    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($paths['thumb_path']));
    expect([$w, $h])->toBe([480, 360]);
});

it('upload banyak foto di galeri menyimpan WebP + thumbnail', function (): void {
    actingAs(userWithRole(RoleName::KoordinatorMedpub));

    livewire(CreateGallery::class)
        ->fillForm([
            'title' => 'Malam Keakraban',
            'slug' => 'malam-keakraban',
            'new_photos' => [
                UploadedFile::fake()->image('1.jpg', 1200, 800),
                UploadedFile::fake()->image('2.png', 800, 1200),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $gallery = Gallery::where('slug', 'malam-keakraban')->firstOrFail();

    expect($gallery->photos)->toHaveCount(2);
    $gallery->photos->each(function ($photo): void {
        expect($photo->path)->toEndWith('.webp');
        Storage::disk('public')->assertExists([$photo->path, $photo->thumb_path]);
    });
});

it('menolak berkas yang bukan gambar di galeri', function (): void {
    actingAs(userWithRole(RoleName::KoordinatorMedpub));

    livewire(CreateGallery::class)
        ->fillForm([
            'title' => 'Album Uji',
            'slug' => 'album-uji',
            'new_photos' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')],
        ])
        ->call('create')
        ->assertHasFormErrors(['new_photos']);

    expect(Gallery::where('slug', 'album-uji')->exists())->toBeFalse();
});

it('halaman edit album berisi foto dapat dibuka dan menambah foto baru', function (): void {
    $user = userWithRole(RoleName::KoordinatorMedpub);
    $gallery = Gallery::factory()->create();
    GalleryResource::attachUploadedPhotos($gallery, [UploadedFile::fake()->image('a.jpg', 600, 400)]);

    actingAs($user)->get("/panel-bem/galleries/{$gallery->id}/edit")->assertOk();

    livewire(EditGallery::class, ['record' => $gallery->getRouteKey()])
        ->fillForm(['new_photos' => [UploadedFile::fake()->image('b.jpg', 600, 400)]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($gallery->photos()->count())->toBe(2);
});

it('menghapus album ikut menghapus berkas fotonya', function (): void {
    actingAs(userWithRole(RoleName::KoordinatorMedpub));
    $gallery = Gallery::factory()->create();
    GalleryResource::attachUploadedPhotos($gallery, [UploadedFile::fake()->image('x.jpg', 600, 400)]);
    $photo = $gallery->photos()->first();

    $gallery->delete();

    Storage::disk('public')->assertMissing([$photo->path, $photo->thumb_path]);
});
