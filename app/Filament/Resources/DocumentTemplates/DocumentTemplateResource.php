<?php

namespace App\Filament\Resources\DocumentTemplates;

use App\Filament\Resources\DocumentTemplates\Pages\ManageDocumentTemplates;
use App\Models\DocumentTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Template dokumen Drive (folder.manage: Sekretaris & Super Admin). Dijaga DocumentTemplatePolicy; dikecualikan dari Shield. */
class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'template dokumen';

    protected static ?string $pluralModelLabel = 'template dokumen';

    protected static ?string $navigationLabel = 'Template Dokumen';

    protected static ?string $slug = 'template-dokumen';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama template')->required()->maxLength(150)->unique(ignoreRecord: true),
            Toggle::make('has_letterhead')->label('Pakai kop BEM saat export')->default(true)
                ->helperText('Kop (logo, nama kabinet, kontak) diambil dari Pengaturan Website saat dokumen di-export.'),
            RichEditor::make('content')->label('Isi template')->required()->columnSpanFull()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                    ['bulletList', 'orderedList', 'blockquote', 'table', 'horizontalRule'],
                    ['undo', 'redo'],
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Template')->searchable()->weight('semibold'),
                IconColumn::make('has_letterhead')->label('Kop BEM')->boolean(),
                TextColumn::make('updated_at')->label('Diubah')->since(),
            ])
            ->recordActions([EditAction::make()->modalWidth('5xl'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDocumentTemplates::route('/')];
    }
}
