<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BackupInventory;
use App\Services\BackupRunner;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * Backup (Fase 10) — khusus Super Admin aktif (berlapis 2FA wajib).
 * Daftar & unduh berkas backup (ZIP terenkripsi BACKUP_ARCHIVE_PASSWORD), buat backup database sekarang.
 * Berguna di hosting tanpa SSH dan untuk menyimpan salinan di luar server selama R2 belum dipakai.
 */
class Backups extends Page
{
    protected static ?string $slug = 'backup';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Backup';

    protected static ?string $title = 'Backup';

    protected string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->is_active && $user->isSuperAdmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runDatabaseBackup')
                ->label('Backup database sekarang')->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Membuat backup database saat ini (beberapa detik). Backup berkas penuh berjalan otomatis tiap Minggu.')
                ->action(function (): void {
                    $result = app(BackupRunner::class)->runDatabaseBackup();

                    app(ActivityLogger::class)->log('backup.manual_run', null, [
                        'ok' => $result['ok'],
                        'message' => mb_substr($result['message'], 0, 300),
                    ]);

                    $result['ok']
                        ? Notification::make()->title('Backup database selesai')->success()->send()
                        : Notification::make()->title('Backup gagal')->body($result['message'])->danger()->persistent()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $inventory = app(BackupInventory::class);

        try {
            $files = $inventory->files();
            $error = null;
        } catch (Throwable $e) {
            $files = collect();
            $error = 'Disk backup tidak bisa dibaca: '.$e->getMessage();
        }

        return [
            'files' => $files->map(fn (array $f) => [
                'name' => $f['name'],
                'size' => Format::bytes($f['size']),
                'date' => Format::date($f['date']).', '.$f['date']->timezone('Asia/Jakarta')->format('H.i'),
                'url' => route('panel.backups.download', ['file' => $f['name']]),
            ]),
            'total' => Format::bytes($files->sum('size')),
            'latest' => $files->first(),
            'error' => $error,
            'disk' => $inventory->diskName(),
            'offsite' => $inventory->usesOffsiteStorage(),
            'encrypted' => filled(config('backup.backup.password')),
        ];
    }
}
