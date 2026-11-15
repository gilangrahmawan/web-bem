<?php

namespace App\Filament\Resources\Downloads\Schemas;

use App\Enums\DownloadCategory;
use App\Models\Download;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DownloadForm
{
    /** PDF, DOCX, XLSX (AGENTS.md B4: dokumen ≤ 10 MB, validasi mimes + mimetypes). */
    public const MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(200),
                        Select::make('category')
                            ->label('Kategori')
                            ->options(collect(DownloadCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        FileUpload::make('file_path')
                            ->label('Berkas')
                            ->required()
                            ->disk(Download::DISK)
                            ->directory(Download::DIRECTORY)
                            ->visibility('private')
                            ->maxSize(10240)
                            ->acceptedFileTypes(self::MIME_TYPES)
                            ->rules(['mimes:pdf,docx,xlsx'])
                            ->storeFileNamesIn('original_name')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'bin')
                            )
                            ->downloadable()
                            ->helperText('PDF, DOCX, atau XLSX, maksimal 10 MB. Disimpan privat; publik mengunduh lewat halaman /unduhan.')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Tampilkan di halaman publik')
                            ->default(true),
                    ]),
            ]);
    }
}
