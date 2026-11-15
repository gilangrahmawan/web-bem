<?php

namespace App\Http\Controllers\Public;

use App\Enums\ProgramStatus;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Program kerja per divisi pada periode aktif. */
class ProgramController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = Period::current();
        $status = ProgramStatus::tryFrom((string) $request->query('status'));

        $divisions = collect();

        if ($period) {
            $programScope = fn ($q) => $q->where('period_id', $period->id)
                ->when($status, fn ($q) => $q->where('status', $status->value));

            $divisions = Division::query()
                ->whereHas('programs', $programScope)
                ->with(['programs' => fn ($q) => $programScope($q)->orderBy('starts_on')->orderBy('id')])
                ->orderBy('id')
                ->get();
        }

        return view('public.programs', [
            'period' => $period,
            'divisions' => $divisions,
            'activeStatus' => $status,
        ]);
    }
}
