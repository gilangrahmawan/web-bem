<?php

namespace App\Enums;

/**
 * Status aspirasi (AGENTS.md C7):
 * new → in_review → resolved, atau in_review → escalated → in_study → returned → resolved.
 * Bisa rejected dengan alasan (ditampilkan ke pengirim).
 */
enum AspirationStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Escalated = 'escalated';
    case InStudy = 'in_study';
    case Returned = 'returned';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InReview => 'Ditinjau Humas',
            self::Escalated => 'Dieskalasi ke Ketua/Wakil',
            self::InStudy => 'Dikaji Divisi Kajian',
            self::Returned => 'Hasil kajian kembali ke Humas',
            self::Resolved => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    /** Label untuk pengirim di halaman cek status publik. */
    public function publicLabel(): string
    {
        return match ($this) {
            self::New => 'Diterima',
            self::InReview => 'Sedang ditinjau',
            self::Escalated, self::InStudy, self::Returned => 'Sedang ditindaklanjuti',
            self::Resolved => 'Selesai',
            self::Rejected => 'Tidak dapat ditindaklanjuti',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InReview => 'warning',
            self::Escalated => 'danger',
            self::InStudy => 'primary',
            self::Returned => 'warning',
            self::Resolved => 'success',
            self::Rejected => 'gray',
        };
    }

    /** Warna grafik (brand + netral). */
    public function chartColor(): string
    {
        return match ($this) {
            self::New => '#000078',
            self::InReview => '#FFDB1A',
            self::Escalated => '#DC2626',
            self::InStudy => '#232361',
            self::Returned => '#F59E0B',
            self::Resolved => '#15803D',
            self::Rejected => '#B6B6B7',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Rejected], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
