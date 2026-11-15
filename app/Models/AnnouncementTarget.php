<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Target pengumuman: target_type = role | division | user, target_id = id terkait. */
class AnnouncementTarget extends Model
{
    public const ROLE = 'role';

    public const DIVISION = 'division';

    public const USER = 'user';

    /** @var list<string> */
    protected $fillable = [
        'announcement_id',
        'target_type',
        'target_id',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
