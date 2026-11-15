<?php

namespace App\Http\Controllers\Public;

use App\Enums\DownloadCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\SearchesWithLike;
use App\Models\Download;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    use SearchesWithLike;

    public function index(Request $request): View
    {
        $category = DownloadCategory::fromSlug($request->query('kategori'));
        $search = $this->likeTerm($request->query('q'));

        $downloads = Download::query()
            ->active()
            ->when($category, fn ($q) => $q->where('category', $category->value))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', $search)
                ->orWhere('description', 'like', $search)))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('public.downloads', [
            'downloads' => $downloads,
            'activeCategory' => $category,
            'search' => trim((string) $request->query('q')),
        ]);
    }

    /**
     * Mengalirkan berkas dari disk privat. Berkas nonaktif / hilang → 404.
     */
    public function file(Download $download): StreamedResponse
    {
        $disk = Storage::disk(Download::DISK);

        abort_unless($download->is_active && $disk->exists($download->file_path), 404);

        $download->increment('download_count');

        return $disk->download($download->file_path, $download->original_name, [
            'Content-Type' => $download->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
