<?php

namespace App\Enums;

/**
 * Keputusan pada satu tahap persetujuan.
 * `auto_approved` = tahap koordinator dilewati karena penulisnya Koordinator Medpub (keputusan pemilik).
 * `taken_down` = konten terbit diturunkan & dikembalikan untuk revisi.
 */
enum ApprovalDecision: string
{
    case Approved = 'approved';
    case AutoApproved = 'auto_approved';
    case Revision = 'revision';
    case Rejected = 'rejected';
    case TakenDown = 'taken_down';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::AutoApproved => 'Dilewati (diajukan Koordinator Medpub)',
            self::Revision => 'Minta revisi',
            self::Rejected => 'Ditolak',
            self::TakenDown => 'Diturunkan untuk revisi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved, self::AutoApproved => 'success',
            self::Revision, self::TakenDown => 'warning',
            self::Rejected => 'danger',
        };
    }

    public function isApproval(): bool
    {
        return in_array($this, [self::Approved, self::AutoApproved], true);
    }
}
