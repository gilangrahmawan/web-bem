<?php

namespace App\Http\Controllers\Public;

use App\Enums\PostCategory;
use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\SearchesWithLike;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    use SearchesWithLike;

    public function index(Request $request): View
    {
        $category = PostCategory::fromSlug($request->query('kategori'));
        $search = $this->likeTerm($request->query('q'));

        $posts = Post::query()
            ->published()
            ->ofTypes(PostType::newsTypes())
            ->when($category, fn ($q) => $q->where('category', $category->value))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', $search)
                ->orWhere('excerpt', 'like', $search)
                ->orWhere('body', 'like', $search)))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('public.news.index', [
            'posts' => $posts,
            'activeCategory' => $category,
            'search' => trim((string) $request->query('q')),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->ofTypes(PostType::newsTypes())
            ->where('slug', $slug)
            ->with('author')
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->ofTypes(PostType::newsTypes())
            ->whereKeyNot($post->id)
            ->when($post->category, fn ($q) => $q->where('category', $post->category->value))
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('public.news.show', [
            'post' => $post,
            'related' => $related,
            'backRoute' => 'public.news.index',
            'backLabel' => 'Berita',
        ]);
    }
}
