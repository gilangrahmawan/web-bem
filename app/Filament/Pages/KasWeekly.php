<?php

namespace App\Filament\Pages;

use App\Models\Division;
use App\Models\User;
use App\Notifications\KasPaidNotification;
use App\Services\KasService;
use App\Support\Format;
use App\Support\Kas\KasExport;
use App\Support\Kas\KasMemberSummary;
use App\Support\Kas\KasWeek;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Kas Mingguan (Bendahara). Grid pengurus × minggu pada periode aktif.
 * - kas.view_all (bendahara, super_admin, pembina): melihat & export.
 * - kas.manage (bendahara, super_admin): klik sel untuk catat/batal, catat beberapa minggu,
 *   atur nominal & tanggal mulai kas, atur mulai wajib per pengurus.
 * Semua perubahan lewat KasService (otorisasi diulang, transaction, activity log).
 */
class KasWeekly extends Page
{
    protected static ?string $slug = 'kas-mingguan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'BPH';

    protected static ?string $navigationLabel = 'Kas Mingguan';

    protected static ?string $title = 'Kas Mingguan';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.kas-weekly';

    /** ID divisi dari <select> (string; kosong = semua divisi). */
    public ?string $divisionId = null;

    /** 'Y-m' atau 'all'. */
    public string $month = 'all';

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && ($user->can('kas.view_all') || $user->can('kas.manage'));
    }

    public function mount(): void
    {
        $current = $this->kas()->week($this->kas()->currentWeekNumber());
        $this->month = $current?->monthKey() ?? 'all';
    }

    protected function canManage(): bool
    {
        return $this->user()->can('kas.manage');
    }

    protected function getViewData(): array
    {
        $kas = $this->kas();
        $weeks = $kas->weeks();
        $summaries = $this->filteredSummaries();

        return [
            'problem' => $kas->setupProblem(),
            'amount' => $kas->weeklyAmount(),
            'startDate' => $kas->startDate(),
            'currentWeek' => $kas->currentWeekNumber(),
            'weeks' => $this->month === 'all'
                ? $weeks
                : array_values(array_filter($weeks, fn (KasWeek $w) => $w->monthKey() === $this->month)),
            'months' => collect($weeks)
                ->mapWithKeys(fn (KasWeek $w) => [$w->monthKey() => $w->start->locale('id')->translatedFormat('F Y')])
                ->all(),
            'divisions' => Division::query()->orderBy('name')->pluck('name', 'id'),
            'summaries' => $summaries,
            'totals' => [
                'collected' => $summaries->sum(fn (KasMemberSummary $s) => $s->paidTotal()),
                'arrears' => $summaries->sum(fn (KasMemberSummary $s) => $s->arrears),
                'clear' => $summaries->filter(fn (KasMemberSummary $s) => $s->isClear())->count(),
                'count' => $summaries->count(),
            ],
            'canManage' => $this->canManage(),
        ];
    }

    /** Klik sel grid: catat bila belum dibayar, batalkan bila sudah (konfirmasi di browser). */
    #[Exposed]
    public function toggle(int $userId, int $week): void
    {
        $member = $this->kas()->payersQuery()->find($userId);

        if ($member === null) {
            Notification::make()->title('Pengurus tidak ditemukan')->danger()->send();

            return;
        }

        $summary = $this->kas()->summaryFor($member);

        $this->run(halt: false, callback: function () use ($member, $week, $summary): string {
            if ($summary?->isPaid($week)) {
                $this->kas()->cancelWeek($this->user(), $member, $week);

                return "Pembayaran minggu ke-{$week} {$member->name} dibatalkan";
            }

            $this->kas()->recordWeeks($this->user(), $member, [$week]);

            return "Kas minggu ke-{$week} {$member->name} dicatat";
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportXlsx')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->authorize(fn (): bool => static::canAccess())
                ->action(fn (): BinaryFileResponse => $this->export('xlsx')),
            Action::make('exportCsv')
                ->label('CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->authorize(fn (): bool => static::canAccess())
                ->action(fn (): BinaryFileResponse => $this->export('csv')),
            $this->settingsAction(),
        ];
    }

    public function settingsAction(): Action
    {
        return Action::make('kasSettings')
            ->label('Pengaturan kas')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->authorize(fn (): bool => $this->canManage())
            ->modalHeading('Pengaturan kas')
            ->modalDescription('Perubahan nominal hanya berlaku untuk pembayaran berikutnya dan perhitungan tunggakan; riwayat pembayaran tidak berubah.')
            ->fillForm(fn (): array => [
                'amount' => $this->kas()->weeklyAmount(),
                'start_date' => $this->kas()->startDate()?->toDateString(),
            ])
            ->schema([
                TextInput::make('amount')
                    ->label('Nominal kas per minggu')
                    ->prefix('Rp')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1_000_000)
                    ->required(),
                DatePicker::make('start_date')
                    ->label('Tanggal mulai kas')
                    ->native(false)
                    ->displayFormat('j F Y')
                    ->helperText('Minggu ke-1 = Senin pertama pada/setelah tanggal ini. Kosongkan bila belum ditentukan (tidak ada tunggakan).'),
            ])
            ->action(function (array $data): void {
                $this->run(function () use ($data): string {
                    $this->kas()->updateSettings($this->user(), (int) $data['amount'], $data['start_date'] ?? null);

                    return 'Pengaturan kas disimpan';
                });
            });
    }

    /** Catat N minggu belum dibayar paling awal untuk satu pengurus (satu notifikasi). */
    public function recordWeeksAction(): Action
    {
        return Action::make('recordWeeks')
            ->authorize(fn (): bool => $this->canManage())
            ->modalHeading(fn (array $arguments): string => 'Catat kas — '.($this->member($arguments)?->name ?? ''))
            ->modalSubmitActionLabel('Catat pembayaran')
            ->fillForm(['count' => 1])
            ->schema(fn (array $arguments): array => [
                TextInput::make('count')
                    ->label('Jumlah minggu')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(26)
                    ->required()
                    ->live(debounce: 300),
                Text::make(function (Get $get) use ($arguments): string {
                    $member = $this->member($arguments);
                    $weeks = $member ? $this->kas()->nextUnpaidWeeks($member, max(1, min(26, (int) $get('count')))) : [];

                    return $weeks === []
                        ? 'Tidak ada minggu yang bisa dicatat.'
                        : 'Minggu ke-'.KasPaidNotification::weekList($weeks).' · '.Format::rupiah(count($weeks) * $this->kas()->weeklyAmount());
                }),
            ])
            ->action(function (array $data, array $arguments): void {
                $member = $this->member($arguments);

                if ($member === null) {
                    return;
                }

                $weeks = $this->kas()->nextUnpaidWeeks($member, max(1, min(26, (int) $data['count'])));

                $this->run(function () use ($member, $weeks): string {
                    $this->kas()->recordWeeks($this->user(), $member, $weeks);

                    return 'Kas minggu ke-'.KasPaidNotification::weekList($weeks)." {$member->name} dicatat";
                });
            });
    }

    /** Tanggal mulai wajib kas per pengurus (yang bergabung belakangan). */
    public function memberStartAction(): Action
    {
        return Action::make('memberStart')
            ->authorize(fn (): bool => $this->canManage())
            ->modalHeading(fn (array $arguments): string => 'Mulai wajib kas — '.($this->member($arguments)?->name ?? ''))
            ->modalDescription('Kewajiban kas dihitung dari tanggal yang lebih akhir antara tanggal mulai kas umum dan tanggal ini.')
            ->fillForm(fn (array $arguments): array => ['kas_starts_on' => $this->member($arguments)?->kas_starts_on?->toDateString()])
            ->schema([
                DatePicker::make('kas_starts_on')
                    ->label('Mulai wajib kas')
                    ->native(false)
                    ->displayFormat('j F Y')
                    ->helperText('Kosongkan untuk mengikuti tanggal mulai kas umum.'),
            ])
            ->action(function (array $data, array $arguments): void {
                $member = $this->member($arguments);

                if ($member === null) {
                    return;
                }

                $this->run(function () use ($member, $data): string {
                    $this->kas()->setMemberStart($this->user(), $member, $data['kas_starts_on'] ?? null);

                    return "Mulai wajib kas {$member->name} disimpan";
                });
            });
    }

    protected function export(string $format): BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);

        $kas = $this->kas();
        $path = KasExport::write(KasExport::rows($this->filteredSummaries(), $kas->weeks(), $kas->currentWeekNumber()), $format);

        return response()->download($path, KasExport::fileName($format))->deleteFileAfterSend();
    }

    /** @return Collection<int, KasMemberSummary> */
    protected function filteredSummaries(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $this->kas()->memberSummaries(is_numeric($this->divisionId) ? (int) $this->divisionId : null)
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn (KasMemberSummary $s) => str_contains(mb_strtolower($s->user->name), $search)
            ))
            ->values();
    }

    /**
     * Jalankan aksi service; tampilkan pesan Indonesia bila ditolak.
     * $halt = true di dalam action Filament (modal tetap terbuka); false untuk method Livewire biasa.
     */
    protected function run(callable $callback, bool $halt = true): void
    {
        try {
            $message = $callback();
        } catch (AuthorizationException) {
            Notification::make()->title('Tidak diizinkan')->danger()->send();

            $halt ? throw new Halt : null;

            return;
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak bisa diproses')->body(collect($e->errors())->flatten()->first())->danger()->send();

            $halt ? throw new Halt : null;

            return;
        }

        $this->kas()->flush();
        Notification::make()->title($message)->success()->send();
    }

    /** @param  array<string, mixed>  $arguments */
    protected function member(array $arguments): ?User
    {
        $id = $arguments['user'] ?? null;

        return is_numeric($id) ? $this->kas()->payersQuery()->find((int) $id) : null;
    }

    protected function kas(): KasService
    {
        return app(KasService::class);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
