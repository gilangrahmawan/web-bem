<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Gallery;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $static = collect([
                'public.home', 'public.profile', 'public.structure', 'public.news.index',
                'public.studies.index', 'public.programs.index', 'public.galleries.index',
                'public.downloads.index', 'public.calendar', 'public.contact',
            ])->map(fn (string $name): array => ['loc' => route($name), 'lastmod' => null]);

            $posts = Post::query()->published()->latest('published_at')->get(['id', 'type', 'slug', 'updated_at'])
                ->map(fn (Post $post): array => ['loc' => $post->publicUrl(), 'lastmod' => $post->updated_at]);

            $divisions = Division::query()->get(['slug', 'updated_at'])
                ->map(fn (Division $d): array => ['loc' => route('public.divisions.show', $d->slug), 'lastmod' => $d->updated_at]);

            $galleries = Gallery::query()->whereHas('photos')->get(['slug', 'updated_at'])
                ->map(fn (Gallery $g): array => ['loc' => route('public.galleries.show', $g->slug), 'lastmod' => $g->updated_at]);

            return view('public.sitemap', [
                'urls' => $static->concat($divisions)->concat($posts)->concat($galleries),
            ])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = app()->environment('production')
            ? ['User-agent: *', 'Disallow: /panel-bem', 'Disallow: /livewire', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /']; // lokal/staging tidak diindeks

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
