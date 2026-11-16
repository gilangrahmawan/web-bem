<?php

namespace App\Services;

use App\Enums\DueType;
use App\Enums\RoleName;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\KasWeeklyPayment;
use App\Models\Period;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DuePaidNotification;
use App\Notifications\KasPaidNotification;
use App\Support\Kas\KasMemberSummary;
use App\Support\Kas\KasWeek;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kas mingguan & iuran tambahan (AGENTS.md C3 Bendahara, Fase 4).
 *
 * Aturan (keputusan pemilik):
 * - Minggu ke-1 = Senin pertama pada/setelah setting `kas_start_date`. Kosong → tidak ada minggu & tunggakan.
 * - Wajib kas: pengurus aktif yang punya role, kecuali Pembina. Mulai wajib per user =
 *   Senin pertama pada/setelah tanggal yang lebih akhir antara kas_start_date dan users.kas_starts_on.
 * - Tunggakan = minggu wajib yang sudah dimulai & belum dibayar × nominal saat ini. Bayar di muka boleh.
 * - Iuran sekali bayar ditagih penuh; iuran bulanan ditagih per bulan sejak tenggat pertama,
 *   target total sampai akhir periode. Cicilan tidak boleh melebihi sisa target.
 *
 * Didaftarkan scoped (satu instance per request) sehingga hasil hitung di-memo untuk beberapa widget.
 */
class KasService
{
    public const DEFAULT_AMOUNT = 5000;

    /** Batas jumlah minggu bila periode tidak punya tanggal selesai. */
    private const MAX_WEEKS = 60;

    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(private readonly ActivityLogger $logger) {}

    // --- Pengaturan ------------------------------------------------------------------------

    public function startDate(): ?CarbonImmutable
    {
        $value = Setting::get('kas_start_date');

        return filled($value) ? CarbonImmutable::parse((string) $value)->startOfDay() : null;
    }

    public function weeklyAmount(): int
    {
        return max(0, (int) (Setting::get('kas_weekly_amount') ?? self::DEFAULT_AMOUNT));
    }

    public function period(): ?Period
    {
        return $this->memo['period'] ??= Period::current();
    }

    /** Alasan kas belum bisa dihitung (null = siap). */
    public function setupProblem(): ?string
    {
        return match (true) {
            $this->period() === null => 'Belum ada periode kepengurusan aktif.',
            $this->startDate() === null => 'Tanggal mulai kas belum diatur.',
            $this->weeks() === [] => 'Tanggal mulai kas berada setelah akhir periode aktif.',
            default => null,
        };
    }

    /** @throws AuthorizationException|ValidationException */
    public function updateSettings(User $actor, int $amount, ?string $startDate): void
    {
        $this->authorize($actor, 'kas.manage');

        if ($amount < 0 || $amount > 1_000_000) {
            throw ValidationException::withMessages(['amount' => 'Nominal kas harus antara Rp0 dan Rp1.000.000.']);
        }

        $startDate = filled($startDate) ? CarbonImmutable::parse($startDate)->toDateString() : null;
        $before = ['kas_weekly_amount' => $this->weeklyAmount(), 'kas_start_date' => Setting::get('kas_start_date')];

        DB::transaction(function () use ($actor, $amount, $startDate, $before): void {
            Setting::set('kas_weekly_amount', $amount);
            Setting::set('kas_start_date', $startDate);

            $this->logger->log('kas.settings_updated', null, [
                'before' => $before,
                'after' => ['kas_weekly_amount' => $amount, 'kas_start_date' => $startDate],
            ], $actor);
        });

        $this->flush();
    }

    /** Atur tanggal mulai wajib kas seorang pengurus (null = ikut kas_start_date). */
    public function setMemberStart(User $actor, User $member, ?string $date): void
    {
        $this->authorize($actor, 'kas.manage');

        $old = $member->kas_starts_on?->toDateString();
        $new = filled($date) ? CarbonImmutable::parse($date)->toDateString() : null;

        DB::transaction(function () use ($actor, $member, $old, $new): void {
            $member->forceFill(['kas_starts_on' => $new])->save();

            $this->logger->log('kas.member_start_updated', $member, ['before' => $old, 'after' => $new], $actor);
        });

        $this->flush();
    }

    // --- Minggu & peserta ------------------------------------------------------------------

    /** @return list<KasWeek> */
    public function weeks(): array
    {
        if (array_key_exists('weeks', $this->memo)) {
            return $this->memo['weeks'];
        }

        $start = $this->startDate();
        $period = $this->period();

        if ($start === null || $period === null) {
            return $this->memo['weeks'] = [];
        }

        $first = self::firstMondayOnOrAfter($start);
        $last = $period->ends_on ? CarbonImmutable::parse($period->ends_on)->startOfDay() : null;
        $weeks = [];

        for ($n = 1; $n <= self::MAX_WEEKS; $n++) {
            $weekStart = $first->addWeeks($n - 1);

            if ($last !== null && $weekStart->greaterThan($last)) {
                break;
            }

            $weeks[] = new KasWeek($n, $weekStart);
        }

        return $this->memo['weeks'] = $weeks;
    }

    public function week(int $number): ?KasWeek
    {
        return $this->weeks()[$number - 1] ?? null;
    }

    /** Nomor minggu berjalan (0 = belum ada minggu yang dimulai). */
    public function currentWeekNumber(): int
    {
        $today = $this->today();
        $current = 0;

        foreach ($this->weeks() as $week) {
            if ($week->hasStarted($today)) {
                $current = $week->number;
            }
        }

        return $current;
    }

    /** Minggu pertama wajib kas user ini (null = belum/tidak wajib dalam periode). */
    public function firstWeekFor(User $user): ?int
    {
        $start = $this->startDate();

        if ($start === null) {
            return null;
        }

        $effective = $user->kas_starts_on && $user->kas_starts_on->greaterThan($start)
            ? CarbonImmutable::parse($user->kas_starts_on)->startOfDay()
            : $start;
        $firstMonday = self::firstMondayOnOrAfter($effective);

        foreach ($this->weeks() as $week) {
            if ($week->start->greaterThanOrEqualTo($firstMonday)) {
                return $week->number;
            }
        }

        return null;
    }

    /** Query pengurus wajib kas: aktif, punya role, bukan Pembina. */
    public function payersQuery(?int $divisionId = null): Builder
    {
        return User::query()
            ->active()
            ->whereHas('roles')
            // Pembina & pengaju kegiatan (bukan pengurus BEM) tidak wajib kas.
            ->withoutRole([RoleName::Pembina->value, RoleName::PengajuKegiatan->value])
            ->when($divisionId, fn (Builder $q, int $id) => $q->where('division_id', $id));
    }

    public function isPayer(User $user): bool
    {
        return $this->payersQuery()->whereKey($user->getKey())->exists();
    }

    // --- Ringkasan -------------------------------------------------------------------------

    /** @return Collection<int, KasMemberSummary> */
    public function memberSummaries(?int $divisionId = null): Collection
    {
        $key = 'summaries:'.($divisionId ?? 'all');

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $users = $this->payersQuery($divisionId)
            ->with('division')
            ->leftJoin('divisions', 'divisions.id', '=', 'users.division_id')
            ->orderByRaw('divisions.name is null')
            ->orderBy('divisions.name')
            ->orderBy('users.name')
            ->select('users.*')
            ->get();

        $payments = $this->paymentsFor($users->modelKeys());

        return $this->memo[$key] = $users->map(
            fn (User $user): KasMemberSummary => $this->summarize($user, $payments[$user->getKey()] ?? [])
        );
    }

    /** Kas Saya. Null bila user bukan pengurus wajib kas (mis. Pembina). */
    public function summaryFor(User $user): ?KasMemberSummary
    {
        if (! $this->isPayer($user)) {
            return null;
        }

        return $this->summarize($user, $this->paymentsFor([$user->getKey()])[$user->getKey()] ?? []);
    }

    /**
     * @return array{collected: int, arrears: int, payers: int, clear: int, arrears_people: int}
     */
    public function totals(?int $divisionId = null): array
    {
        $summaries = $this->memberSummaries($divisionId);
        $period = $this->period();

        $collected = $period
            ? (int) KasWeeklyPayment::query()->where('period_id', $period->getKey())
                ->when($divisionId, fn (Builder $q, int $id) => $q->whereHas('user', fn (Builder $q) => $q->where('division_id', $id)))
                ->sum('amount')
            : 0;

        return [
            'collected' => $collected,
            'arrears' => $summaries->sum(fn (KasMemberSummary $s) => $s->arrears),
            'payers' => $summaries->count(),
            'clear' => $summaries->filter(fn (KasMemberSummary $s) => $s->isClear())->count(),
            'arrears_people' => $summaries->reject(fn (KasMemberSummary $s) => $s->isClear())->count(),
        ];
    }

    /**
     * Kas terkumpul per minggu kas (minggu ke-1 s.d. minggu berjalan, plus minggu depan yang sudah dibayar di muka).
     *
     * @return array<int, int> [nomor minggu => total rupiah]
     */
    public function collectedPerWeek(): array
    {
        $period = $this->period();

        if ($period === null || $this->weeks() === []) {
            return [];
        }

        $sums = KasWeeklyPayment::query()
            ->where('period_id', $period->getKey())
            ->selectRaw('week_number, SUM(amount) as total')
            ->groupBy('week_number')
            ->pluck('total', 'week_number')
            ->map(fn ($v) => (int) $v)
            ->all();

        $last = max($this->currentWeekNumber(), $sums === [] ? 0 : max(array_keys($sums)));
        $result = [];

        for ($n = 1; $n <= $last; $n++) {
            $result[$n] = $sums[$n] ?? 0;
        }

        return $result;
    }

    /** @return array<string, int> [nama divisi => tunggakan rupiah] */
    public function arrearsByDivision(): array
    {
        return $this->memberSummaries()
            ->groupBy(fn (KasMemberSummary $s) => $s->user->division?->name ?? 'Tanpa divisi')
            ->map(fn (Collection $group) => $group->sum(fn (KasMemberSummary $s) => $s->arrears))
            ->all();
    }

    // --- Catat & batal kas mingguan --------------------------------------------------------

    /**
     * Catat pembayaran beberapa minggu sekaligus (satu notifikasi terima kasih).
     *
     * @param  list<int>  $weekNumbers
     * @return Collection<int, KasWeeklyPayment>
     *
     * @throws AuthorizationException|ValidationException
     */
    public function recordWeeks(User $actor, User $member, array $weekNumbers): Collection
    {
        $this->authorize($actor, 'kas.manage');
        $this->ensureReady();

        if (! $this->isPayer($member)) {
            throw ValidationException::withMessages(['member' => "{$member->name} bukan pengurus wajib kas."]);
        }

        $weekNumbers = array_values(array_unique(array_map('intval', $weekNumbers)));
        sort($weekNumbers);

        if ($weekNumbers === []) {
            throw ValidationException::withMessages(['weeks' => 'Pilih minimal satu minggu.']);
        }

        $firstWeek = $this->firstWeekFor($member);

        foreach ($weekNumbers as $number) {
            if ($this->week($number) === null) {
                throw ValidationException::withMessages(['weeks' => "Minggu ke-{$number} tidak ada di periode ini."]);
            }

            if ($firstWeek === null || $number < $firstWeek) {
                throw ValidationException::withMessages(['weeks' => "Minggu ke-{$number} sebelum tanggal mulai kas {$member->name}."]);
            }
        }

        $period = $this->period();
        $amount = $this->weeklyAmount();

        $already = KasWeeklyPayment::query()
            ->where('period_id', $period->getKey())
            ->where('user_id', $member->getKey())
            ->whereIn('week_number', $weekNumbers)
            ->pluck('week_number')
            ->all();

        if ($already !== []) {
            throw ValidationException::withMessages(['weeks' => 'Minggu ke-'.implode(', ', $already).' sudah dibayar.']);
        }

        try {
            $payments = DB::transaction(function () use ($actor, $member, $weekNumbers, $period, $amount): Collection {
                $payments = collect($weekNumbers)->map(fn (int $number) => KasWeeklyPayment::query()->create([
                    'period_id' => $period->getKey(),
                    'user_id' => $member->getKey(),
                    'week_number' => $number,
                    'week_start' => $this->week($number)->start,
                    'amount' => $amount,
                    'paid_at' => now(),
                    'recorded_by' => $actor->getKey(),
                ]));

                $this->logger->log('kas.paid', $member, [
                    'period_id' => $period->getKey(),
                    'weeks' => $weekNumbers,
                    'amount_per_week' => $amount,
                    'total' => $amount * count($weekNumbers),
                ], $actor);

                return $payments;
            });
        } catch (UniqueConstraintViolationException) {
            // Klik ganda bersamaan: database menolak minggu yang sama dua kali.
            throw ValidationException::withMessages(['weeks' => 'Minggu tersebut sudah dibayar.']);
        }

        $this->flush();
        $member->notify(new KasPaidNotification($weekNumbers, $amount * count($weekNumbers)));

        return $payments;
    }

    /** @throws AuthorizationException|ValidationException */
    public function cancelWeek(User $actor, User $member, int $weekNumber): void
    {
        $this->authorize($actor, 'kas.manage');
        $period = $this->period();

        $payment = $period
            ? KasWeeklyPayment::query()
                ->where('period_id', $period->getKey())
                ->where('user_id', $member->getKey())
                ->where('week_number', $weekNumber)
                ->first()
            : null;

        if ($payment === null) {
            throw ValidationException::withMessages(['weeks' => "Minggu ke-{$weekNumber} belum dibayar."]);
        }

        DB::transaction(function () use ($actor, $member, $payment): void {
            $payment->delete();

            $this->logger->log('kas.cancelled', $member, [
                'period_id' => $payment->period_id,
                'week' => $payment->week_number,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at->toDateTimeString(),
            ], $actor);
        });

        $this->flush();
    }

    /**
     * N minggu belum dibayar paling awal (mulai minggu wajib pertama; boleh minggu depan = bayar di muka).
     *
     * @return list<int>
     */
    public function nextUnpaidWeeks(User $member, int $count): array
    {
        $first = $this->firstWeekFor($member);
        $period = $this->period();

        if ($first === null || $period === null || $count < 1) {
            return [];
        }

        $paid = KasWeeklyPayment::query()
            ->where('period_id', $period->getKey())
            ->where('user_id', $member->getKey())
            ->pluck('week_number')
            ->all();

        return collect($this->weeks())
            ->map(fn (KasWeek $w) => $w->number)
            ->filter(fn (int $n) => $n >= $first && ! in_array($n, $paid, true))
            ->take($count)
            ->values()
            ->all();
    }

    // --- Iuran tambahan --------------------------------------------------------------------

    /** @return Collection<int, User> */
    public function dueParticipants(Due $due): Collection
    {
        return $due->applies_to_all
            ? $this->payersQuery()->orderBy('name')->get()
            : $due->members()->where('users.is_active', true)->orderBy('name')->get();
    }

    public function isDueParticipant(Due $due, User $user): bool
    {
        return $due->applies_to_all
            ? $this->isPayer($user)
            : $due->members()->where('users.id', $user->getKey())->where('users.is_active', true)->exists();
    }

    /** Jumlah bulan tagihan total (bulanan: dari tenggat pertama sampai akhir periode, minimal 1). */
    public function dueMonths(Due $due): int
    {
        if ($due->type === DueType::Once) {
            return 1;
        }

        $end = $this->period()?->ends_on;

        if ($end === null) {
            return 12;
        }

        $months = 0;
        $billing = CarbonImmutable::parse($due->due_date);

        while ($billing->lessThanOrEqualTo(CarbonImmutable::parse($end)) && $months < 120) {
            $months++;
            $billing = CarbonImmutable::parse($due->due_date)->addMonthsNoOverflow($months);
        }

        return max(1, $months);
    }

    /** Target total per peserta. */
    public function dueTarget(Due $due): int
    {
        return $due->amount * $this->dueMonths($due);
    }

    /** Tagihan berjalan per peserta per hari ini. */
    public function dueBilled(Due $due): int
    {
        if ($due->type === DueType::Once) {
            return $due->amount;
        }

        $today = $this->today();
        $months = 0;

        while ($months < $this->dueMonths($due)
            && CarbonImmutable::parse($due->due_date)->addMonthsNoOverflow($months)->lessThanOrEqualTo($today)) {
            $months++;
        }

        return $due->amount * $months;
    }

    /**
     * @return array{target: int, billed: int, paid: int, arrears: int, remaining: int}
     */
    public function dueStatus(Due $due, User $user, ?int $paid = null): array
    {
        $paid ??= (int) DuePayment::query()->where('due_id', $due->getKey())->where('user_id', $user->getKey())->sum('amount');
        $target = $this->dueTarget($due);
        $billed = $this->dueBilled($due);

        return [
            'target' => $target,
            'billed' => $billed,
            'paid' => $paid,
            'arrears' => max(0, $billed - $paid),
            'remaining' => max(0, $target - $paid),
        ];
    }

    /**
     * Progres iuran: total terkumpul dari peserta vs total target semua peserta.
     *
     * @return array{collected: int, target: int, participants: int, percent: int}
     */
    public function dueProgress(Due $due): array
    {
        $participants = $this->dueParticipants($due);
        $collected = (int) DuePayment::query()
            ->where('due_id', $due->getKey())
            ->whereIn('user_id', $participants->modelKeys())
            ->sum('amount');
        $target = $this->dueTarget($due) * $participants->count();

        return [
            'collected' => $collected,
            'target' => $target,
            'participants' => $participants->count(),
            'percent' => $target > 0 ? (int) min(100, round($collected / $target * 100)) : 0,
        ];
    }

    /**
     * Iuran yang masih punya sisa untuk user ini (Kas Saya).
     *
     * @return list<array{due: Due, target: int, billed: int, paid: int, arrears: int, remaining: int}>
     */
    public function outstandingDuesFor(User $user): array
    {
        $paid = DuePayment::query()->where('user_id', $user->getKey())
            ->selectRaw('due_id, SUM(amount) as total')->groupBy('due_id')->pluck('total', 'due_id');

        return Due::query()->orderBy('due_date')->get()
            ->filter(fn (Due $due) => $this->isDueParticipant($due, $user))
            ->map(fn (Due $due) => ['due' => $due, ...$this->dueStatus($due, $user, (int) ($paid[$due->getKey()] ?? 0))])
            ->filter(fn (array $row) => $row['remaining'] > 0)
            ->values()
            ->all();
    }

    /** @throws AuthorizationException|ValidationException */
    public function recordDuePayment(User $actor, Due $due, User $member, int $amount, ?string $note = null): DuePayment
    {
        $this->authorize($actor, 'due.manage');

        if (! $this->isDueParticipant($due, $member)) {
            throw ValidationException::withMessages(['user_id' => "{$member->name} bukan peserta iuran ini."]);
        }

        $status = $this->dueStatus($due, $member);

        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembayaran minimal Rp1.']);
        }

        if ($amount > $status['remaining']) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal melebihi sisa tagihan (Rp'.number_format($status['remaining'], 0, ',', '.').').',
            ]);
        }

        $payment = DB::transaction(function () use ($actor, $due, $member, $amount, $note, $status): DuePayment {
            $payment = DuePayment::query()->create([
                'due_id' => $due->getKey(),
                'user_id' => $member->getKey(),
                'amount' => $amount,
                'paid_at' => now(),
                'note' => filled($note) ? $note : null,
                'recorded_by' => $actor->getKey(),
            ]);

            $this->logger->log('due.paid', $payment, [
                'due' => $due->name,
                'member_id' => $member->getKey(),
                'amount' => $amount,
                'remaining' => $status['remaining'] - $amount,
            ], $actor);

            return $payment;
        });

        $member->notify(new DuePaidNotification($due, $amount, $status['remaining'] - $amount));

        return $payment;
    }

    /** @throws AuthorizationException */
    public function deleteDuePayment(User $actor, DuePayment $payment): void
    {
        $this->authorize($actor, 'due.manage');

        DB::transaction(function () use ($actor, $payment): void {
            $payment->delete();

            $this->logger->log('due.payment_deleted', $payment->due, [
                'member_id' => $payment->user_id,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at->toDateTimeString(),
            ], $actor);
        });
    }

    // --- Internal --------------------------------------------------------------------------

    /** @param  array<int, int>  $payments */
    private function summarize(User $user, array $payments): KasMemberSummary
    {
        $first = $this->firstWeekFor($user);
        $current = $this->currentWeekNumber();
        $due = $first === null || $current < $first ? [] : range($first, $current);
        $unpaid = array_values(array_filter($due, fn (int $n) => ! array_key_exists($n, $payments)));

        return new KasMemberSummary(
            user: $user,
            firstWeek: $first,
            payments: $payments,
            dueWeeks: $due,
            unpaidWeeks: $unpaid,
            arrears: count($unpaid) * $this->weeklyAmount(),
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<int, int>> [user_id => [minggu => nominal]]
     */
    private function paymentsFor(array $userIds): array
    {
        $period = $this->period();

        if ($period === null || $userIds === []) {
            return [];
        }

        $result = [];

        KasWeeklyPayment::query()
            ->where('period_id', $period->getKey())
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'week_number', 'amount'])
            ->each(function (KasWeeklyPayment $p) use (&$result): void {
                $result[$p->user_id][$p->week_number] = $p->amount;
            });

        return $result;
    }

    /** @throws ValidationException */
    private function ensureReady(): void
    {
        if ($problem = $this->setupProblem()) {
            throw ValidationException::withMessages(['kas' => $problem]);
        }
    }

    /** @throws AuthorizationException */
    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->is_active || ! $actor->can($permission)) {
            throw new AuthorizationException('Anda tidak berhak melakukan aksi ini.');
        }
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    public static function firstMondayOnOrAfter(CarbonInterface $date): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return $date->isMonday() ? $date : $date->next(CarbonInterface::MONDAY);
    }
}
