<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Period;
use Illuminate\Contracts\View\View;

class DivisionController extends Controller
{
    public function __invoke(Division $division): View
    {
        $period = Period::current();

        $programs = $period
            ? $division->programs()->where('period_id', $period->id)->orderBy('starts_on')->orderBy('id')->get()
            : collect();

        $members = $period
            ? $division->structureMembers()->where('period_id', $period->id)->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view('public.division', compact('division', 'period', 'programs', 'members'));
    }
}
