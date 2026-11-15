<?php

namespace App\Enums;

enum ProgramStatus: string
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Rencana',
            self::Ongoing => 'Berjalan',
            self::Done => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Ongoing => 'warning',
            self::Done => 'success',
        };
    }
}
