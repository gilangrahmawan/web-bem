<?php

namespace App\Filament\Resources\Galleries\Schemas;

use App\Filament\Support\WebpImageUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GalleryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Album')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul album')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(160)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        DatePicker::make('event_date')
                            ->label('Tanggal kegiatan')
                            ->native(false)
                            ->displayFormat('d F Y'),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Tambah foto')
                    ->description('Pilih atau seret beberapa foto sekaligus. Foto dikompres ke WebP dan dibuatkan thumbnail.')
                    ->columnSpanFull()
                    ->schema([
                        WebpImageUpload::resizeInBrowser(FileUpload::make('new_photos'))
                            ->label('Foto baru')
                            ->multiple()
                            ->maxFiles(30)
                            ->maxParallelUploads(2)
                            ->storeFiles(false)
                            ->panelLayout('grid')
                            ->helperText('JPG/PNG/WebP, hingga 30 foto per simpan. Foto besar otomatis diperkecil di browser sebelum diunggah.'),
                    ]),

                Section::make('Foto di album')
                    ->columnSpanFull()
                    ->visibleOn('edit')
                    ->schema([
                        Repeater::make('photos')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->addable(false)
                            ->grid(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->deleteAction(fn ($action) => $action->requiresConfirmation())
                            ->schema([
                                FileUpload::make('thumb_path')
                                    ->hiddenLabel()
                                    ->image()
                                    ->disk('public')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('caption')
                                    ->label('Keterangan foto')
                                    ->maxLength(200),
                            ]),
                    ]),
            ]);
    }
}
