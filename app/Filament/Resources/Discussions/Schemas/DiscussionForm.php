<?php

namespace App\Filament\Resources\Discussions\Schemas;

use App\Enums\DiscussionStatus;
use App\Filament\Support\WebpImageUpload;
use App\Models\Discussion;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiscussionForm
{
    public static function configure(Schema $schema): Schema
    {
        $canSetDocs = (bool) Filament::auth()->user()?->can('setDocsLink', Discussion::class);

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->label('Judul')->required()->maxLength(200)->columnSpanFull(),
                    Select::make('status')->label('Status')->options(DiscussionStatus::options())
                        ->default(DiscussionStatus::Draft->value)->required(),
                    TextInput::make('docs_link')
                        ->label('Link Google Docs')
                        ->url()
                        ->rule('url:http,https')
                        ->maxLength(500)
                        ->disabled(! $canSetDocs)
                        ->dehydrated($canSetDocs)
                        ->helperText($canSetDocs ? null : 'Hanya Koordinator Kajian yang dapat mengisi link Docs.'),
                    WebpImageUpload::make('photo', 'discussions', 1600)
                        ->label('Foto')
                        ->columnSpanFull(),
                    Repeater::make('reference_links')
                        ->label('Link referensi')
                        ->schema([
                            TextInput::make('url')->label('URL')->url()->rule('url:http,https')->required()->maxLength(500),
                            TextInput::make('label')->label('Keterangan')->maxLength(150),
                        ])
                        ->columns(2)
                        ->addActionLabel('Tambah link')
                        ->defaultItems(0)
                        ->maxItems(20)
                        ->columnSpanFull(),
                    RichEditor::make('body')
                        ->label('Isi pembahasan')
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'strike', 'link'],
                            ['h2', 'h3', 'blockquote', 'bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
