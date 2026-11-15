<?php

namespace App\Models;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Konten website (artikel, postingan, press release, kajian publik).
 * Alur persetujuan dikerjakan di Fase 6. Isi HTML selalu disanitasi.
 */
class Post extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'cover',
        'social_caption',
        'category',
        'attachment_path',
        'attachment_name',
    ];

    protected function casts(): array
    {
        return [
            'type' => PostType::class,
            'status' => PostStatus::class,
            'category' => PostCategory::class,
            'published_at' => 'datetime',
            'social_posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Sanitasi saat disimpan (lapisan pertama; tampilan juga disanitasi ulang).
        static::saving(function (Post $post): void {
            if ($post->isDirty('body') && $post->body !== null) {
                $post->body = clean($post->body, 'post');
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Hanya konten yang benar-benar terbit (status published + tanggal terbit sudah lewat).
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param  Builder<Post>  $query */
    public function scopeOfTypes(Builder $query, array $types): void
    {
        $query->whereIn('type', $types);
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /** HTML isi yang aman ditampilkan dengan {!! !!}. */
    public function safeBody(): string
    {
        return clean((string) $this->body, 'post');
    }

    public function summary(int $limit = 160): string
    {
        $text = $this->excerpt ?: strip_tags((string) $this->body);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode($text)) ?? ''), $limit);
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? Storage::disk('public')->url($this->cover) : null;
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
    }

    public function publicUrl(): string
    {
        return in_array($this->type?->value, PostType::studyTypes(), true)
            ? route('public.studies.show', $this->slug)
            : route('public.news.show', $this->slug);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'konten';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
