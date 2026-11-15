<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatat activity_logs (AGENTS.md B4 → Data).
 * Jangan pernah memasukkan password atau rahasia ke $properties.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  bool  $recordIp  false untuk kiriman publik (aspirasi anonim tidak boleh menyimpan IP, AGENTS.md B4)
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
        bool $recordIp = true,
    ): ActivityLog {
        $user ??= Auth::user();

        return ActivityLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => ! $recordIp || (app()->runningInConsole() && ! app()->runningUnitTests()) ? null : request()->ip(),
        ]);
    }
}
