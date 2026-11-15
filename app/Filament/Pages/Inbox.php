<?php

namespace App\Filament\Pages;

use App\Enums\AnnouncementType;
use App\Models\AnnouncementRecipient;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Kotak Masuk: pengumuman & surat milik user yang login saja (tanpa ID user dari browser).
 * Dibuka → otomatis ditandai dibaca. Surat: tombol "Sudah ditindaklanjuti" & "Balas".
 */
class Inbox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'kotak-masuk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Kotak Masuk';

    protected static ?string $title = 'Kotak Masuk';

    protected string $view = 'filament.pages.inbox';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $unread = AnnouncementRecipient::query()->where('user_id', $user->getKey())->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AnnouncementRecipient::query()
                ->where('user_id', Filament::auth()->id())
                ->with('announcement.creator'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('announcement.type')->label('Jenis')->badge()
                    ->formatStateUsing(fn (AnnouncementType $state): string => $state->label())
                    ->color(fn (AnnouncementType $state): string => $state->color()),
                TextColumn::make('announcement.title')->label('Judul')->searchable()->wrap()
                    ->weight(fn (AnnouncementRecipient $r): string => $r->read_at ? 'normal' : 'bold')
                    ->description(fn (AnnouncementRecipient $r): ?string => $r->read_at ? null : 'Belum dibaca'),
                TextColumn::make('announcement.creator.name')->label('Dari')->placeholder('-'),
                TextColumn::make('followed_up_at')->label('Tindak lanjut')->placeholder('-')
                    ->formatStateUsing(fn (AnnouncementRecipient $r): string => $r->followed_up_at ? 'Sudah' : '-'),
                TextColumn::make('created_at')->label('Diterima')
                    ->formatStateUsing(fn (AnnouncementRecipient $r): string => Format::date($r->created_at)),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')
                    ->options([AnnouncementType::Announcement->value => 'Pengumuman', AnnouncementType::Letter->value => 'Surat'])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('announcement', fn (Builder $a) => $a->where('type', $data['value']))
                        : $query),
                TernaryFilter::make('unread')->label('Status')
                    ->trueLabel('Belum dibaca')->falseLabel('Sudah dibaca')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('read_at'),
                        false: fn (Builder $query) => $query->whereNotNull('read_at'),
                    ),
            ])
            ->recordAction('open')
            ->recordActions([
                Action::make('open')
                    ->label('Buka')
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->modalHeading(fn (AnnouncementRecipient $r): string => $r->announcement->title)
                    ->modalWidth('2xl')
                    ->mountUsing(fn (AnnouncementRecipient $record) => $this->service()->markRead($this->user(), $record))
                    ->modalContent(fn (AnnouncementRecipient $record) => view('filament.pages.partials.inbox-message', ['recipient' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('followUp')
                    ->label('Sudah ditindaklanjuti')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AnnouncementRecipient $r): bool => $r->announcement->type === AnnouncementType::Letter && $r->followed_up_at === null)
                    ->action(fn (AnnouncementRecipient $record) => $this->run(fn () => $this->service()->markFollowedUp($this->user(), $record), 'Ditandai sudah ditindaklanjuti')),
                Action::make('reply')
                    ->label('Balas')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (AnnouncementRecipient $r): bool => $r->announcement->type === AnnouncementType::Letter)
                    ->fillForm(fn (AnnouncementRecipient $r): array => ['reply' => $r->reply])
                    ->schema([Textarea::make('reply')->label('Balasan untuk pengirim')->required()->rows(4)->maxLength(2000)])
                    ->action(fn (AnnouncementRecipient $record, array $data) => $this->run(fn () => $this->service()->reply($this->user(), $record, $data['reply']), 'Balasan terkirim')),
            ])
            ->emptyStateHeading('Kotak masuk kosong')
            ->emptyStateDescription('Pengumuman dan surat untuk Anda akan tampil di sini.')
            ->emptyStateIcon(Heroicon::OutlinedInbox);
    }

    protected function run(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (AuthorizationException) {
            Notification::make()->title('Tidak diizinkan')->danger()->send();

            throw new Halt;
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->first())->danger()->send();

            throw new Halt;
        }

        Notification::make()->title($success)->success()->send();
    }

    protected function service(): AnnouncementService
    {
        return app(AnnouncementService::class);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
