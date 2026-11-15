<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Keputusan persetujuan konten pada satu tahap & putaran review. Dibuat lewat PostWorkflowService. */
class PostApproval extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'post_id',
        'user_id',
        'round',
        'stage',
        'decision',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'stage' => ApprovalStage::class,
            'decision' => ApprovalDecision::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
