<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Unduh gambar" untuk medsos: memberikan gambar ASLI resolusi penuh (disk privat) bila ada,
 * selain itu cover WebP. Hanya untuk yang berhak melihat konten tersebut; selain itu 404.
 */
class PostImageController extends Controller
{
    public function __invoke(int $post): StreamedResponse
    {
        $record = Post::query()->find($post);

        abort_if($record === null || Gate::denies('view', $record), 404);

        $baseName = Str::slug($record->title) ?: 'konten';

        if ($record->cover_original_path && Storage::disk(Post::ORIGINAL_DISK)->exists($record->cover_original_path)) {
            $extension = pathinfo($record->cover_original_path, PATHINFO_EXTENSION);

            return Storage::disk(Post::ORIGINAL_DISK)->download($record->cover_original_path, "{$baseName}.{$extension}", [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort_unless($record->cover && Storage::disk('public')->exists($record->cover), 404);

        return Storage::disk('public')->download($record->cover, "{$baseName}.webp", ['X-Content-Type-Options' => 'nosniff']);
    }
}
