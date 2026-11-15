<?php

namespace App\Http\Controllers\Public;

use App\Enums\PostType;
use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Post;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $latestNews = Post::query()
            ->published()
            ->ofTypes(PostType::newsTypes())
            ->latest('published_at')
            ->limit(6)
            ->get();

        $latestStudies = Post::query()
            ->published()
            ->ofTypes(PostType::studyTypes())
            ->latest('published_at')
            ->limit(3)
            ->get();

        $albums = Gallery::query()
            ->whereHas('photos')
            ->with('coverPhoto')
            ->withCount('photos')
            ->latest('event_date')
            ->limit(4)
            ->get();

        return view('public.home', compact('latestNews', 'latestStudies', 'albums'));
    }
}
