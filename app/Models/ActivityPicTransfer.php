<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Permintaan pindah penanggung jawab kegiatan (status: pending/approved/rejected). */
class ActivityPicTransfer extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'student_activity_id',
        'from_user_id',
        'to_user_id',
        'requested_by',
        'status',
        'decided_by',
        'decided_at',
        'note',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(StudentActivity::class, 'student_activity_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
