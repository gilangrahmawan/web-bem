<?php

namespace App\Filament\Resources\Downloads;

use App\Filament\Resources\Downloads\Pages\CreateDownload;
use App\Filament\Resources\Downloads\Pages\EditDownload;
use App\Filament\Resources\Downloads\Pages\ListDownloads;
use App\Filament\Resources\Downloads\Schemas\DownloadForm;
use App\Filament\Resources\Downloads\Tables\DownloadsTable;
use App\Models\Download;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'BPH';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'berkas unduhan';

    protected static ?string $pluralModelLabel = 'berkas unduhan';

    protected static ?string $navigationLabel = 'Berkas Unduhan';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DownloadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DownloadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDownloads::route('/'),
            'create' => CreateDownload::route('/create'),
            'edit' => EditDownload::route('/{record}/edit'),
        ];
    }

    /**
     * Melengkapi ukuran & tipe MIME dari berkas yang tersimpan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillFileMetadata(array $data): array
    {
        $disk = Storage::disk(Download::DISK);

        if (! empty($data['file_path']) && $disk->exists($data['file_path'])) {
            $data['size'] = $disk->size($data['file_path']);
            $data['mime_type'] = $disk->mimeType($data['file_path']) ?: null;
        }

        return $data;
    }
}
