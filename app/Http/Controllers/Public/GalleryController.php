<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Contracts\View\View;

class GalleryController extends Controller
{
    public function index(): View
    {
        $albums = Gallery::query()
            ->whereHas('photos')
            ->with('coverPhoto')
            ->withCount('photos')
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(12);

        return view('public.galleries.index', compact('albums'));
    }

    public function show(Gallery $gallery): View
    {
        $gallery->load('photos');

        abort_if($gallery->photos->isEmpty(), 404);

        return view('public.galleries.show', compact('gallery'));
    }
}
