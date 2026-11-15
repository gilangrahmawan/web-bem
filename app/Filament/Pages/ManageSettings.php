<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\ActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Pengaturan website (tabel settings). Dijaga permission kustom `setting.manage`.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    /** Kunci yang dikelola halaman ini (whitelist; kunci lain diabaikan). */
    public const KEYS = [
        'cabinet_name',
        'vision',
        'mission',
        'leader_welcome',
        'contact_email',
        'contact_phone',
        'contact_address',
        'social_instagram',
        'social_tiktok',
        'social_youtube',
        'kas_weekly_amount',
    ];

    protected static ?string $slug = 'pengaturan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 80;

    protected static ?string $navigationLabel = 'Pengaturan Website';

    protected static ?string $title = 'Pengaturan Website';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->can('setting.manage');
    }

    public function mount(): void
    {
        $values = Setting::allValues();

        $this->form->fill(collect(self::KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $values[$key] ?? null])
            ->all());
    }

    #[Unexposed]
    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas & profil')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('cabinet_name')->label('Nama kabinet')->required()->maxLength(150),
                        Textarea::make('vision')->label('Visi')->rows(3)->maxLength(2000),
                        Textarea::make('mission')->label('Misi')->rows(6)->maxLength(5000)
                            ->helperText('Satu poin per baris.'),
                        Textarea::make('leader_welcome')->label('Sambutan Ketua')->rows(6)->maxLength(5000),
                    ]),
                Section::make('Kontak & media sosial')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('contact_email')->label('Email')->email()->maxLength(191),
                        TextInput::make('contact_phone')->label('Telepon / WA')->tel()->maxLength(30),
                        Textarea::make('contact_address')->label('Alamat sekretariat')->rows(2)->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('social_instagram')->label('Instagram (URL)')->url()->maxLength(255),
                        TextInput::make('social_tiktok')->label('TikTok (URL)')->url()->maxLength(255),
                        TextInput::make('social_youtube')->label('YouTube (URL)')->url()->maxLength(255),
                    ]),
                Section::make('Keuangan')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('kas_weekly_amount')
                            ->label('Nominal kas mingguan')
                            ->prefix('Rp')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1_000_000)
                            ->required(),
                    ]),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Simpan pengaturan')->submit('save'),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    #[Exposed]
    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $data['kas_weekly_amount'] = (int) $data['kas_weekly_amount'];

        $before = Setting::allValues();
        $changed = [];

        DB::transaction(function () use ($data, $before, &$changed): void {
            foreach (self::KEYS as $key) {
                $value = $data[$key] ?? null;

                if (($before[$key] ?? null) !== $value) {
                    Setting::set($key, $value);
                    $changed[] = $key;
                }
            }

            if ($changed !== []) {
                app(ActivityLogger::class)->log('setting.updated', null, ['keys' => $changed]);
            }
        });

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
