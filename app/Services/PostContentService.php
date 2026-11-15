<?php

namespace App\Services;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\TaskStatus;
use App\Models\EventAssignee;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Draft konten & berkas cover.
 *
 * Cover (keputusan pemilik Fase 6): website memakai WebP terkompres (disk public), sedangkan gambar
 * ASLI resolusi penuh disimpan di disk privat untuk diunduh tim medsos. Pasangan WebP→asli dicatat di
 * cache sisi server saat upload — path asli tidak pernah diterima dari browser.
 */
class PostContentService
{
    public const COVER_DIRECTORY = 'posts';

    private const FIELDS = ['type', 'title', 'excerpt', 'body', 'cover', 'social_caption', 'category', 'attachment_path', 'attachment_name'];

    public function __construct(
        private readonly ImageService $images,
        private readonly ActivityLogger $logger,
    ) {}

    /** Dipanggil komponen upload: simpan asli (privat) + WebP (public); kembalikan path WebP. */
    public function storeUploadedCover(UploadedFile $file): string
    {
        $uuid = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');

        $original = Storage::disk(Post::ORIGINAL_DISK)->putFileAs(Post::ORIGINAL_DIRECTORY, $file, "{$uuid}.{$extension}");
        $webp = $this->images->storeWebp($file, self::COVER_DIRECTORY, 1600, 'public', $uuid);

        Cache::put($this->cacheKey($webp), ['path' => $original, 'name' => $file->getClientOriginalName()], now()->addDay());

        return $webp;
    }

    /** @param  array<string, mixed>  $data */
    public function createDraft(User $actor, array $data): Post
    {
        $type = $this->allowedType($actor, $data['type'] ?? null);
        $attributes = $this->attributes($data, $type);

        return DB::transaction(function () use ($actor, $attributes): Post {
            $post = new Post($attributes);
            $post->forceFill([
                'slug' => Post::uniqueSlug($attributes['title']),
                'author_id' => $actor->getKey(),
                'status' => PostStatus::Draft,
                'review_round' => 0,
                ...$this->originalFor($attributes['cover'] ?? null),
            ])->save();

            $this->logger->log('post.created', $post, ['title' => $post->title, 'type' => $post->type->value], $actor);

            return $post;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateDraft(User $actor, Post $post, array $data): Post
    {
        if (Gate::forUser($actor)->denies('update', $post)) {
            throw new AuthorizationException('Konten hanya bisa diubah penulisnya saat draft atau revisi.');
        }

        $type = $this->allowedType($actor, $data['type'] ?? $post->type->value);
        $attributes = $this->attributes($data, $type);

        return DB::transaction(function () use ($actor, $post, $attributes): Post {
            $coverChanged = ($attributes['cover'] ?? null) !== $post->cover;
            $post->fill($attributes);

            if ($coverChanged) {
                $post->forceFill($this->originalFor($attributes['cover'] ?? null));
            }

            if ($post->isDirty('title') && $post->published_at === null) {
                $post->slug = Post::uniqueSlug($post->title, $post->getKey());
            }

            $changed = array_keys($post->getDirty());
            $post->save();

            $this->logger->log('post.updated', $post, ['changed' => $changed], $actor);

            return $post;
        });
    }

    /**
     * Draft konten dari tugas konten yang sudah diserahkan (AGENTS.md Fase 6 poin 4).
     * Penulis = koordinator yang membuat. Hasil gambar → cover WebP + gambar asli.
     */
    public function draftFromTask(User $actor, EventAssignee $assignee): Post
    {
        if (Gate::forUser($actor)->denies('review', $assignee) || Gate::forUser($actor)->denies('createType', [Post::class, PostType::Social])) {
            throw new AuthorizationException('Anda tidak berhak membuat draft dari tugas ini.');
        }

        if ($assignee->status !== TaskStatus::Submitted) {
            throw ValidationException::withMessages(['status' => 'Draft hanya bisa dibuat dari tugas berstatus Diserahkan.']);
        }

        return DB::transaction(function () use ($actor, $assignee): Post {
            $cover = $this->coverFromTaskResult($assignee);

            $post = new Post([
                'type' => PostType::Social,
                'title' => $assignee->event->title,
                'cover' => $cover['cover'] ?? null,
            ]);
            $post->forceFill([
                'slug' => Post::uniqueSlug($assignee->event->title),
                'author_id' => $actor->getKey(),
                'status' => PostStatus::Draft,
                'review_round' => 0,
                'event_assignee_id' => $assignee->getKey(),
                'cover_original_path' => $cover['cover_original_path'] ?? null,
                'cover_original_name' => $cover['cover_original_name'] ?? null,
            ])->save();

            $this->logger->log('post.created_from_task', $post, ['event_assignee_id' => $assignee->getKey()], $actor);

            return $post;
        });
    }

    /** @return array{cover?: string, cover_original_path?: string, cover_original_name?: string} */
    private function coverFromTaskResult(EventAssignee $assignee): array
    {
        $path = $assignee->result_path;
        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $disk = Storage::disk(EventAssignee::DISK);

        if (! $path || ! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) || ! $disk->exists($path)) {
            return [];
        }

        $uuid = Str::uuid()->toString();
        $original = Post::ORIGINAL_DIRECTORY."/{$uuid}.{$extension}";
        Storage::disk(Post::ORIGINAL_DISK)->writeStream($original, $disk->readStream($path));

        // Disalin ke file sementara agar tetap jalan bila disk dipindah ke R2/S3.
        $temp = tempnam(sys_get_temp_dir(), 'cover-');
        file_put_contents($temp, $disk->get($path));

        try {
            $webp = $this->images->storeWebp($temp, self::COVER_DIRECTORY, 1600, 'public', $uuid);
        } finally {
            @unlink($temp);
        }

        return [
            'cover' => $webp,
            'cover_original_path' => $original,
            'cover_original_name' => $assignee->result_original_name ?: basename($path),
        ];
    }

    private function allowedType(User $actor, ?string $value): PostType
    {
        $type = PostType::tryFrom((string) $value);

        if ($type === null || Gate::forUser($actor)->denies('createType', [Post::class, $type])) {
            throw new AuthorizationException('Anda tidak berhak membuat konten bertipe ini.');
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, PostType $type): array
    {
        $attributes = array_intersect_key($data, array_flip(self::FIELDS));
        $attributes['type'] = $type;
        $attributes['title'] = trim((string) ($attributes['title'] ?? ''));

        if ($attributes['title'] === '') {
            throw ValidationException::withMessages(['title' => 'Judul wajib diisi.']);
        }

        // Kategori hanya untuk berita (artikel & postingan).
        $attributes['category'] = in_array($type->value, PostType::newsTypes(), true)
            ? PostCategory::tryFrom((string) ($attributes['category'] ?? ''))
            : null;

        // Cover hanya boleh path hasil upload di folder cover.
        if (filled($attributes['cover'] ?? null) && ! str_starts_with((string) $attributes['cover'], self::COVER_DIRECTORY.'/')) {
            $attributes['cover'] = null;
        }

        return $attributes;
    }

    /** @return array{cover_original_path: ?string, cover_original_name: ?string} */
    private function originalFor(?string $cover): array
    {
        $original = $cover ? Cache::pull($this->cacheKey($cover)) : null;

        return [
            'cover_original_path' => $original['path'] ?? null,
            'cover_original_name' => $original['name'] ?? null,
        ];
    }

    private function cacheKey(string $webpPath): string
    {
        return 'post-cover-original:'.$webpPath;
    }
}
