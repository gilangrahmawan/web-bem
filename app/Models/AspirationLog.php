<?php

namespace App\Models;

use App\Enums\AspirationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat status aspirasi. `note` = catatan INTERNAL, tidak pernah tampil di halaman publik. */
class AspirationLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'aspiration_id',
        'from_status',
        'to_status',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => AspirationStatus::class,
            'to_status' => AspirationStatus::class,
        ];
    }

    public function aspiration(): BelongsTo
    {
        return $this->belongsTo(Aspiration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
