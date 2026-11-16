<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ViewsDrive;
use App\Models\DriveActivity;
use App\Support\Format;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Aktivitas Drive terbaru — hanya pada folder yang boleh dilihat user (tanpa aktivitas "membuka"). */
class DriveActivityWidget extends TableWidget
{
    use ViewsDrive;

    protected static ?int $sort = 62;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Drive: aktivitas terbaru';

    public function table(Table $table): Table
    {
        $visible = $this->visibleFolders();

        return $table
            ->query(fn () => DriveActivity::query()->with('user:id,name')
                ->where('action', '!=', 'open')
                ->when($visible !== null, fn ($query) => $query->whereIn('folder_id', $visible ?: [0])))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Pengurus')->placeholder('Sistem'),
                TextColumn::make('action')->label('Aksi')->badge()
                    ->formatStateUsing(fn (DriveActivity $r): string => $r->actionLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'delete', 'force_delete' => 'danger',
                        'upload', 'create', 'archive' => 'success',
                        'share' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('item_name')->label('Item')->limit(50)
                    ->description(fn (DriveActivity $r): string => $r->item_type->label()),
                TextColumn::make('created_at')->label('Waktu')
                    ->formatStateUsing(fn (DriveActivity $r): string => Format::date($r->created_at).', '.$r->created_at->format('H.i')),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada aktivitas Drive')
            ->emptyStateDescription('Unggahan, dokumen baru, dan perubahan di Drive akan tampil di sini.');
    }
}
