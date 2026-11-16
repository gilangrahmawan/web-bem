<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Template dokumen (surat undangan, peminjaman, proposal, LPJ, notulen). Kop BEM ditambahkan saat export. */
class DocumentTemplate extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'content',
        'has_letterhead',
    ];

    protected function casts(): array
    {
        return [
            'has_letterhead' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DocumentTemplate $template): void {
            $template->content = Document::sanitize($template->content);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
