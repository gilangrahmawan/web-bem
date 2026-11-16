<?php

namespace App\Filament\Widgets;

use App\Enums\DriveFileKind;
use App\Filament\Widgets\Concerns\ViewsDrive;
use App\Models\Document;
use App\Models\DriveFile;
use Filament\Widgets\ChartWidget;

/** Berkas Drive per jenis (donat) — hanya berkas yang boleh dilihat user. */
class DriveFileTypesChart extends ChartWidget
{
    use ViewsDrive;

    protected static ?int $sort = 61;

    protected ?string $heading = 'Drive: berkas per jenis';

    protected ?string $maxHeight = '260px';

    protected ?string $emptyStateHeading = 'Belum ada berkas';

    protected ?string $emptyStateDescription = 'Grafik muncul setelah ada berkas atau dokumen di Drive.';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $visible = $this->visibleFolders();
        $scope = fn ($query) => $visible === null ? $query : $query->whereIn('folder_id', $visible ?: [0]);

        $counts = $scope(DriveFile::query())->selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind');
        $counts[DriveFileKind::Document->value] = $scope(Document::query())->count();

        if ($counts->sum() === 0) {
            return [];
        }

        $kinds = array_values(array_filter(DriveFileKind::cases(), fn (DriveFileKind $k) => ($counts[$k->value] ?? 0) > 0));

        return [
            'datasets' => [[
                'data' => array_map(fn (DriveFileKind $k) => (int) $counts[$k->value], $kinds),
                'backgroundColor' => array_map(fn (DriveFileKind $k) => $k->chartColor(), $kinds),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (DriveFileKind $k) => $k->label(), $kinds),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
            'cutout' => '60%',
        ];
    }
}
