<?php

namespace App\Enums;

/** Tahap persetujuan konten. `publication` = catatan penurunan konten terbit ("Turunkan & revisi"). */
enum ApprovalStage: string
{
    case Coordinator = 'coordinator';
    case Leader = 'leader';
    case Publication = 'publication';

    public function label(): string
    {
        return match ($this) {
            self::Coordinator => 'Koordinator Medpub',
            self::Leader => 'Ketua/Wakil',
            self::Publication => 'Publikasi',
        };
    }
}
