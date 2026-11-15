<?php

namespace App\Http\Controllers\Public;

use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\SearchesWithLike;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Press release & kajian publik. */
class StudyController extends Controller
{
    use SearchesWithLike;

    /** Filter ?jenis= di URL publik. */
    private const TYPE_FILTERS = [
        'press-release' => PostType::PressRelease,
        'kajian' => PostType::Study,
    ];

    public function index(Request $request): View
    {
        $type = self::TYPE_FILTERS[$request->query('jenis')] ?? null;
        $search = $this->likeTerm($request->query('q'));

        $posts = Post::query()
            ->published()
            ->ofTypes($type ? [$type->value] : PostType::studyTypes())
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', $search)
                ->orWhere('excerpt', 'like', $search)
                ->orWhere('body', 'like', $search)))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('public.studies.index', [
            'posts' => $posts,
            'activeType' => $request->query('jenis'),
            'typeFilters' => self::TYPE_FILTERS,
            'search' => trim((string) $request->query('q')),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->ofTypes(PostType::studyTypes())
            ->where('slug', $slug)
            ->with('author')
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->ofTypes(PostType::studyTypes())
            ->whereKeyNot($post->id)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('public.news.show', [
            'post' => $post,
            'related' => $related,
            'backRoute' => 'public.studies.index',
            'backLabel' => 'Kajian & Pernyataan Sikap',
        ]);
    }
}
