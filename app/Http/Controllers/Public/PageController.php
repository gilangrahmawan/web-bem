<?php

namespace App\Http\Controllers\Public;

use App\Enums\DownloadCategory;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Download;
use App\Models\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function profile(): View
    {
        return view('public.profile', [
            'period' => Period::current(),
            'divisions' => Division::query()->orderBy('id')->get(),
        ]);
    }

    /** Bagan BPH & divisi dari periode aktif. */
    public function structure(): View
    {
        $period = Period::current();

        $divisions = collect();

        if ($period) {
            $divisions = Division::query()
                ->whereHas('structureMembers', fn ($q) => $q->where('period_id', $period->id))
                ->with(['structureMembers' => fn ($q) => $q->where('period_id', $period->id)->with('user')->orderBy('sort_order')->orderBy('id')])
                ->orderByRaw("CASE WHEN slug = 'bph' THEN 0 ELSE 1 END")
                ->orderBy('id')
                ->get();
        }

        $unassigned = $period
            ? $period->structureMembers()->whereNull('division_id')->with('user')->orderBy('sort_order')->get()
            : collect();

        return view('public.structure', compact('period', 'divisions', 'unassigned'));
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    /** Halaman informasi pengajuan kegiatan (pengajuan dilakukan setelah login, keputusan Fase 7). */
    public function submissions(): View
    {
        return view('public.submissions', [
            'templates' => Download::query()->active()
                ->whereIn('category', [DownloadCategory::ProposalTemplate->value, DownloadCategory::LpjTemplate->value])
                ->orderBy('category')->orderBy('title')->get(),
        ]);
    }

    public function comingSoon(Request $request): View
    {
        return view('public.coming-soon', [
            'title' => (string) $request->route()->defaults['title'],
        ]);
    }
}
