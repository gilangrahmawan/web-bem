<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Drive;
use App\Models\Document;
use App\Models\DriveFile;
use App\Models\User;
use App\Services\Drive\DriveAccessResolver;
use App\Services\Drive\DriveService;
use App\Support\Format;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Pemakaian penyimpanan Drive (kuota global) — Super Admin, Sekretaris, Pembina. */
class DriveStorageWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 60;

    protected ?string $heading = 'Drive: penyimpanan';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && app(DriveAccessResolver::class)->seesAll($user);
    }

    protected function getStats(): array
    {
        $used = DriveService::usedBytes();
        $quota = DriveService::quotaBytes();
        $percent = round($used / max(1, $quota) * 100, 1);
        $trash = (int) DriveFile::onlyTrashed()->sum('size');
        $url = Drive::getUrl();

        return [
            Stat::make('Terpakai', Format::bytes($used))
                ->description($percent.'% dari '.Format::bytes($quota))
                ->descriptionIcon(Heroicon::OutlinedCircleStack)
                ->color($percent >= 90 ? 'danger' : ($percent >= 75 ? 'warning' : 'primary'))
                ->chart([0, $percent])
                ->url($url),
            Stat::make('Berkas & dokumen', number_format(DriveFile::query()->count() + Document::query()->count(), 0, ',', '.'))
                ->description(DriveFile::query()->count().' berkas · '.Document::query()->count().' dokumen')
                ->descriptionIcon(Heroicon::OutlinedDocumentDuplicate)
                ->url($url),
            Stat::make('Di Sampah', Format::bytes($trash))
                ->description('Dihapus permanen setelah '.DriveService::trashDays().' hari')
                ->descriptionIcon(Heroicon::OutlinedTrash)
                ->color($trash > 0 ? 'warning' : 'gray')
                ->url(Drive::getUrl(['bagian' => 'trash'])),
        ];
    }
}
