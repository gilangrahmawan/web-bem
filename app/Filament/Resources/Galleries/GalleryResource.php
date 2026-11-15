<?php

namespace App\Filament\Resources\Galleries;

use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Galleries\Pages\ListGalleries;
use App\Filament\Resources\Galleries\Schemas\GalleryForm;
use App\Filament\Resources\Galleries\Tables\GalleriesTable;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Services\ImageService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use UnitEnum;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Medpub';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'album';

    protected static ?string $pluralModelLabel = 'galeri';

    protected static ?string $navigationLabel = 'Galeri';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return GalleryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GalleriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }

    /**
     * Mengompres foto baru (WebP + thumbnail) dan menautkannya ke album.
     *
     * @param  array<int, mixed>  $files
     */
    public static function attachUploadedPhotos(Gallery $gallery, array $files): int
    {
        $images = app(ImageService::class);
        $order = (int) $gallery->photos()->max('sort_order');
        $count = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $paths = $images->storeWithThumbnail($file, "galleries/{$gallery->id}");

            GalleryPhoto::query()->create([
                'gallery_id' => $gallery->id,
                'path' => $paths['path'],
                'thumb_path' => $paths['thumb_path'],
                'sort_order' => ++$order,
            ]);

            $count++;
        }

        return $count;
    }
}
