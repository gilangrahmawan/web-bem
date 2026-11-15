<?php

namespace App\Filament\Resources\Dues\RelationManagers;

use App\Models\DuePayment;
use App\Models\User;
use App\Services\KasService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Riwayat pembayaran iuran. Mencatat lewat aksi "Catat bayar" di daftar iuran (KasService). */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Riwayat pembayaran';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'recorder']))
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Pengurus')->searchable(),
                TextColumn::make('amount')->label('Nominal')->formatStateUsing(fn (int $state): string => Format::rupiah($state)),
                TextColumn::make('paid_at')->label('Dicatat')->dateTime('j M Y, H.i'),
                TextColumn::make('recorder.name')->label('Oleh')->placeholder('-'),
                TextColumn::make('note')->label('Catatan')->placeholder('-')->wrap(),
            ])
            ->recordActions([
                Action::make('deletePayment')
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Pembayaran ini akan dihapus dan tercatat di log aktivitas.')
                    ->authorize(fn (): bool => (bool) Filament::auth()->user()?->can('due.manage'))
                    ->action(function (DuePayment $record): void {
                        /** @var User $user */
                        $user = Filament::auth()->user();
                        app(KasService::class)->deleteDuePayment($user, $record);
                        Notification::make()->title('Pembayaran dihapus')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada pembayaran');
    }
}
