<?php

namespace App\Enums;

/** Alur konten (AGENTS.md C7). Perpindahan status diatur di Fase 6. */
enum PostStatus: string
{
    case Draft = 'draft';
    case PendingCoordinator = 'pending_coordinator';
    case PendingLeader = 'pending_leader';
    case Approved = 'approved';
    case Published = 'published';
    case Revision = 'revision';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingCoordinator => 'Menunggu Koordinator',
            self::PendingLeader => 'Menunggu Ketua/Wakil',
            self::Approved => 'Disetujui',
            self::Published => 'Terbit',
            self::Revision => 'Revisi',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingCoordinator, self::PendingLeader => 'warning',
            self::Approved => 'info',
            self::Published => 'success',
            self::Revision => 'warning',
            self::Rejected => 'danger',
        };
    }
}
