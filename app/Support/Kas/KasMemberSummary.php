<?php

namespace App\Support\Kas;

use App\Models\User;

/**
 * Ringkasan kas satu pengurus pada periode aktif.
 *
 * - firstWeek: minggu pertama wajib kas (null = belum wajib / tanggal mulai kas belum diatur).
 * - payments: [nomor minggu => nominal dibayar].
 * - dueWeeks: minggu wajib yang sudah dimulai (jatuh tempo) sampai minggu berjalan.
 * - unpaidWeeks: minggu jatuh tempo yang belum dibayar.
 * - arrears: tunggakan dalam rupiah = jumlah unpaidWeeks × nominal saat ini.
 */
final readonly class KasMemberSummary
{
    /**
     * @param  array<int, int>  $payments
     * @param  list<int>  $dueWeeks
     * @param  list<int>  $unpaidWeeks
     */
    public function __construct(
        public User $user,
        public ?int $firstWeek,
        public array $payments,
        public array $dueWeeks,
        public array $unpaidWeeks,
        public int $arrears,
    ) {}

    public function paidTotal(): int
    {
        return array_sum($this->payments);
    }

    public function paidWeeksCount(): int
    {
        return count($this->payments);
    }

    /** Minggu jatuh tempo yang sudah dibayar. */
    public function paidDueWeeksCount(): int
    {
        return count($this->dueWeeks) - count($this->unpaidWeeks);
    }

    public function isClear(): bool
    {
        return $this->unpaidWeeks === [];
    }

    public function isPaid(int $week): bool
    {
        return array_key_exists($week, $this->payments);
    }

    /** Minggu ini wajib dibayar user (bukan sebelum tanggal mulai kasnya). */
    public function isRequired(int $week): bool
    {
        return $this->firstWeek !== null && $week >= $this->firstWeek;
    }

    public function isOverdue(int $week): bool
    {
        return in_array($week, $this->unpaidWeeks, true);
    }
}
