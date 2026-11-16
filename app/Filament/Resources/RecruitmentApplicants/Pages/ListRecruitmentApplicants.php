<?php

namespace App\Filament\Resources\RecruitmentApplicants\Pages;

use App\Enums\ApplicantStatus;
use App\Filament\Resources\RecruitmentApplicants\RecruitmentApplicantResource;
use App\Models\RecruitmentApplicant;
use App\Support\Format;
use App\Support\SpreadsheetExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Tab per status + export Excel/CSV (pengelola oprec; nilai rata-rata tanpa catatan internal). */
class ListRecruitmentApplicants extends ListRecords
{
    protected static string $resource = RecruitmentApplicantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportXlsx')->label('Excel (.xlsx)')->icon(Heroicon::OutlinedTableCells)
                    ->action(fn (): BinaryFileResponse => $this->export('xlsx')),
                Action::make('exportCsv')->label('CSV')->icon(Heroicon::OutlinedDocumentText)
                    ->action(fn (): BinaryFileResponse => $this->export('csv')),
            ])
                ->label('Export')->icon(Heroicon::OutlinedArrowDownTray)->button()->color('gray')
                ->visible(fn (): bool => self::canExport()),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')];

        foreach (ApplicantStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return $tabs;
    }

    public static function canExport(): bool
    {
        $user = RecruitmentApplicantResource::user();

        return $user !== null && ($user->isSuperAdmin() || $user->can('recruitment.manage'));
    }

    protected function export(string $format): BinaryFileResponse
    {
        abort_unless(self::canExport(), 403);

        $rows = [[
            'Kode', 'Gelombang', 'Nama', 'NIM', 'Prodi', 'Angkatan', 'WhatsApp', 'Email',
            'Pilihan 1', 'Pilihan 2', 'Status', 'Diterima di', 'Jadwal interview', 'Tempat/link interview',
            'Nilai rata-rata', 'Jumlah penilai', 'Tanggal daftar',
        ]];

        $this->getFilteredTableQuery()->withCount('interviews')->orderBy('id')
            ->each(function (RecruitmentApplicant $a) use (&$rows): void {
                $rows[] = [
                    $a->registration_code,
                    $a->recruitment?->name,
                    $a->name,
                    $a->nim,
                    $a->prodi,
                    $a->batch_year,
                    $a->phone ?? ($a->data_purged_at ? '(dihapus)' : ''),
                    $a->email ?? ($a->data_purged_at ? '(dihapus)' : ''),
                    $a->firstChoice?->name,
                    $a->secondChoice?->name ?? '-',
                    $a->status->label(),
                    $a->acceptedDivision?->name ?? '-',
                    $a->interview_at ? $a->interview_at->format('d/m/Y H:i') : '-',
                    $a->interview_location ?? '-',
                    $a->interviews_avg_score !== null ? round((float) $a->interviews_avg_score, 2) : '-',
                    (int) $a->interviews_count,
                    Format::date($a->created_at),
                ];
            });

        $path = SpreadsheetExport::write($rows, $format);

        return response()->download($path, 'pendaftar-oprec-'.now()->format('Y-m-d').'.'.$format)->deleteFileAfterSend();
    }
}
