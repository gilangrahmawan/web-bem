<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CheckRecruitmentResultRequest;
use App\Http\Requests\Public\StoreRecruitmentApplicantRequest;
use App\Models\Recruitment;
use App\Models\RecruitmentApplicant;
use App\Services\RecruitmentService;
use App\Services\TurnstileVerifier;
use App\Support\TrackingCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Oprec publik (tanpa login). Kode pendaftaran hanya lewat session flash (POST → redirect).
 * Halaman hasil menampilkan status & jadwal interview; keputusan akhir baru tampil setelah
 * "Umumkan hasil". Nilai & catatan interview TIDAK PERNAH ditampilkan.
 */
class RecruitmentController extends Controller
{
    public function create(TurnstileVerifier $turnstile): View
    {
        $recruitment = Recruitment::currentlyOpen();

        return view('public.recruitment.create', [
            'recruitment' => $recruitment,
            'divisions' => $recruitment?->openDivisions() ?? collect(),
            'upcoming' => $recruitment ? null : Recruitment::query()->upcoming()->first(),
            'siteKey' => $turnstile->siteKey(),
            'submittedCode' => session('submitted_code'),
        ]);
    }

    public function store(StoreRecruitmentApplicantRequest $request, RecruitmentService $service): RedirectResponse
    {
        $recruitment = Recruitment::currentlyOpen();

        if (! $recruitment) {
            return redirect()->route('public.recruitment.create')
                ->withErrors(['recruitment' => 'Pendaftaran sedang tidak dibuka.']);
        }

        $applicant = $service->register(
            $recruitment,
            $request->safe()->except(['cv', 'photo', 'consent', 'cf-turnstile-response']),
            $request->file('cv'),
            $request->file('photo'),
        );

        return redirect()->route('public.recruitment.create')->with('submitted_code', $applicant->registration_code);
    }

    public function results(TurnstileVerifier $turnstile): View
    {
        $id = session('checked_applicant');
        $applicant = $id
            ? RecruitmentApplicant::query()->with(['recruitment', 'firstChoice', 'secondChoice', 'acceptedDivision'])->find($id)
            : null;

        return view('public.recruitment.results', [
            'siteKey' => $turnstile->siteKey(),
            'applicant' => $applicant,
        ]);
    }

    public function check(CheckRecruitmentResultRequest $request): RedirectResponse
    {
        $code = $request->code();

        $applicant = TrackingCode::isValidFormat($code, 'REG')
            ? RecruitmentApplicant::query()->where('registration_code', $code)->where('nim', $request->nim())->first()
            : null;

        if (! $applicant) {
            return back()->withInput($request->only('registration_code', 'nim'))
                ->withErrors(['registration_code' => 'Data pendaftaran tidak ditemukan. Periksa kode pendaftaran dan NIM Anda.']);
        }

        return redirect()->route('public.recruitment.results')->with('checked_applicant', $applicant->getKey());
    }
}
