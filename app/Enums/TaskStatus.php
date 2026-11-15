<?php

namespace App\Enums;

/**
 * Status tugas konten Medpub (AGENTS.md C7):
 * todo → in_progress → submitted → done, dan bisa dikembalikan ke in_progress.
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Belum Dikerjakan',
            self::InProgress => 'Dikerjakan',
            self::Submitted => 'Diserahkan',
            self::Done => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'warning',
            self::Submitted => 'info',
            self::Done => 'success',
        };
    }

    /** Tugas yang masih perlu dikerjakan (dapat pengingat H-2/H-1). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Todo, self::InProgress], true);
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Todo => [self::InProgress],
            self::InProgress => [self::Submitted],
            self::Submitted => [self::InProgress, self::Done],
            self::Done => [self::InProgress],
        }, true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])->all();
    }
}
