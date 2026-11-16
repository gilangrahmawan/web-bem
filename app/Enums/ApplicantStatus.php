<?php

namespace App\Enums;

/** Status pendaftar oprec (AGENTS.md C7): registered → document_passed → interview → accepted / rejected. */
enum ApplicantStatus: string
{
    case Registered = 'registered';
    case DocumentPassed = 'document_passed';
    case Interview = 'interview';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Terdaftar',
            self::DocumentPassed => 'Lolos berkas',
            self::Interview => 'Interview',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registered => 'gray',
            self::DocumentPassed => 'info',
            self::Interview => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Registered => '#B6B6B7',
            self::DocumentPassed => '#000078',
            self::Interview => '#FFDB1A',
            self::Accepted => '#232361',
            self::Rejected => '#DC2626',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected], true);
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Registered => [self::DocumentPassed, self::Rejected],
            self::DocumentPassed => [self::Interview, self::Rejected],
            self::Interview => [self::Accepted, self::Rejected],
            self::Accepted, self::Rejected => [],
        }, true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
