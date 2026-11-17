<?php

namespace App\Enums;

/** Tujuan form (owner menentukan alurnya; 11A hanya memakai Survey untuk form mandiri BEM). */
enum FormPurpose: string
{
    case Registration = 'registration';
    case ExitTicket = 'exit_ticket';
    case Exam = 'exam';
    case Pretest = 'pretest';
    case Posttest = 'posttest';
    case Survey = 'survey';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Pendaftaran',
            self::ExitTicket => 'Exit ticket',
            self::Exam => 'Ujian',
            self::Pretest => 'Pre-test',
            self::Posttest => 'Post-test',
            self::Survey => 'Survei / kuisioner',
        };
    }
}
