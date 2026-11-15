<?php

namespace App\Models;

use App\Enums\DiscussionStatus;
use Database\Factories\DiscussionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Pembahasan Kajian. Isi (HTML rich editor) disanitasi saat disimpan dan saat ditampilkan.
 * `docs_link` hanya boleh diisi pemegang discussion.set_docs_link (dijaga DiscussionService).
 */
class Discussion extends Model
{
    /** @use HasFactory<DiscussionFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'photo',
        'reference_links',
        'docs_link',
        'body',
        'status',
        'aspiration_id',
        'created_by',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'reference_links' => 'array',
            'status' => DiscussionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Discussion $discussion): void {
            if ($discussion->isDirty('body') && filled($discussion->body)) {
                $discussion->body = clean($discussion->body, 'post');
            }
        });

        static::updated(function (Discussion $discussion): void {
            if ($discussion->wasChanged('photo') && $old = $discussion->getOriginal('photo')) {
                Storage::disk('public')->delete($old);
            }
        });
    }

    public function aspiration(): BelongsTo
    {
        return $this->belongsTo(Aspiration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** HTML aman untuk ditampilkan (disanitasi ulang). */
    public function safeBody(): string
    {
        return clean((string) $this->body, 'post');
    }
}
