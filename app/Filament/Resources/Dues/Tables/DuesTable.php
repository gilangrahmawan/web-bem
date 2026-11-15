<?php

namespace App\Filament\Resources\Dues\Tables;

use App\Enums\DueType;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\User;
use App\Services\KasService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class DuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->columns([
                TextColumn::make('name')->label('Iuran')->searchable()->weight('semibold')->wrap(),
                TextColumn::make('type')->label('Tipe')->badge()
                    ->formatStateUsing(fn (DueType $state): string => $state->label())
                    ->color(fn (DueType $state): string => $state->color()),
                TextColumn::make('amount')->label('Nominal')
                    ->formatStateUsing(fn (Due $record): string => Format::rupiah($record->amount).($record->type === DueType::Monthly ? '/bln' : '')),
                TextColumn::make('due_date')->label('Tenggat')->formatStateUsing(fn (Due $record): string => Format::date($record->due_date))->sortable(),
                TextColumn::make('progress')->label('Terkumpul')
                    ->state(function (Due $record): string {
                        $p = app(KasService::class)->dueProgress($record);

                        return Format::rupiah($p['collected']).' / '.Format::rupiah($p['target'])." ({$p['percent']}%)";
                    })
                    ->description(fn (Due $record): string => app(KasService::class)->dueProgress($record)['participants'].' peserta'),
            ])
            ->recordActions([
                Action::make('recordPayment')
                    ->label('Catat bayar')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->authorize(fn (Due $record): bool => self::user()->can('recordPayment', $record))
                    ->modalHeading(fn (Due $record): string => "Catat pembayaran — {$record->name}")
                    ->schema(fn (Due $record): array => [
                        Select::make('user_id')
                            ->label('Pengurus')
                            ->options(fn () => app(KasService::class)->dueParticipants($record)
                                ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' — sisa '.Format::rupiah(app(KasService::class)->dueStatus($record, $u)['remaining'])]))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) use ($record): void {
                                $user = $state ? User::find($state) : null;
                                $set('amount', $user ? app(KasService::class)->dueStatus($record, $user)['remaining'] : null);
                            }),
                        TextInput::make('amount')
                            ->label('Nominal dibayar')
                            ->prefix('Rp')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->helperText(fn (Get $get): ?string => ($user = $get('user_id') ? User::find($get('user_id')) : null)
                                ? 'Tagihan berjalan '.Format::rupiah(app(KasService::class)->dueStatus($record, $user)['billed'])
                                    .' · sudah dibayar '.Format::rupiah(app(KasService::class)->dueStatus($record, $user)['paid']).'. Boleh dicicil.'
                                : null),
                        TextInput::make('note')->label('Catatan (opsional)')->maxLength(255),
                    ])
                    ->action(function (Due $record, array $data): void {
                        try {
                            app(KasService::class)->recordDuePayment(self::user(), $record, User::findOrFail($data['user_id']), (int) $data['amount'], $data['note'] ?? null);
                        } catch (AuthorizationException) {
                            Notification::make()->title('Tidak diizinkan')->danger()->send();

                            throw new Halt;
                        } catch (ValidationException $e) {
                            Notification::make()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->first())->danger()->send();

                            throw new Halt;
                        }

                        Notification::make()->title('Pembayaran iuran dicatat')->success()->send();
                    }),
                Action::make('participants')
                    ->label('Status peserta')
                    ->icon(Heroicon::OutlinedUsers)
                    ->color('gray')
                    ->modalHeading(fn (Due $record): string => "Status peserta — {$record->name}")
                    ->modalWidth('3xl')
                    ->modalContent(function (Due $record) {
                        $kas = app(KasService::class);
                        $paid = DuePayment::query()->where('due_id', $record->id)
                            ->selectRaw('user_id, SUM(amount) as total')->groupBy('user_id')->pluck('total', 'user_id');

                        return view('filament.resources.dues.participants', [
                            'rows' => $kas->dueParticipants($record)->load('division')->map(fn (User $u) => [
                                'user' => $u,
                                ...$kas->dueStatus($record, $u, (int) ($paid[$u->id] ?? 0)),
                            ]),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Iuran yang sudah punya pembayaran tidak bisa dihapus.'),
            ])
            ->emptyStateHeading('Belum ada iuran tambahan')
            ->emptyStateDescription('Buat iuran seperti PDH atau tabungan jalan-jalan; pembayarannya boleh dicicil.')
            ->emptyStateIcon(Heroicon::OutlinedWallet);
    }

    private static function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
