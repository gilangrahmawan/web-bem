<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat versi dokumen (maks. 1 versi per menit per user). */
class DocumentVersion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'document_id',
        'content',
        'saved_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
