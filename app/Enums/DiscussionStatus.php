<?php

namespace App\Enums;

/** Status pembahasan Kajian (AGENTS.md C7): draft → discussed → done. */
enum DiscussionStatus: string
{
    case Draft = 'draft';
    case Discussed = 'discussed';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Discussed => 'Dibahas',
            self::Done => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Discussed => 'warning',
            self::Done => 'success',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Draft => '#B6B6B7',
            self::Discussed => '#FFDB1A',
            self::Done => '#232361',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
