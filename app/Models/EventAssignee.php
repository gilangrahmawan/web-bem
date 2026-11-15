<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\EventAssigneeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Penanggung jawab tugas konten. Berkas hasil di disk privat, diunduh lewat
 * TaskResultController (berotorisasi). ZIP hanya disimpan, tidak pernah diekstrak.
 */
class EventAssignee extends Model
{
    /** @use HasFactory<EventAssigneeFactory> */
    use HasFactory;

    public const DISK = 'local';

    public const DIRECTORY = 'task-results';

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'result_path',
        'result_original_name',
        'result_url',
        'submitted_at',
        'reminded_h2_at',
        'reminded_h1_at',
    ];

    protected $attributes = [
        'status' => 'todo',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'submitted_at' => 'datetime',
            'reminded_h2_at' => 'datetime',
            'reminded_h1_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (EventAssignee $assignee): void {
            if ($assignee->wasChanged('result_path') && $old = $assignee->getOriginal('result_path')) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        static::deleted(function (EventAssignee $assignee): void {
            if ($assignee->result_path) {
                Storage::disk(self::DISK)->delete($assignee->result_path);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasResult(): bool
    {
        return filled($this->result_path) || filled($this->result_url);
    }
}
