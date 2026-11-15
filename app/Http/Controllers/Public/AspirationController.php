<?php

namespace App\Http\Controllers\Public;

use App\Enums\AspirationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CheckAspirationStatusRequest;
use App\Http\Requests\Public\StoreAspirationRequest;
use App\Models\Aspiration;
use App\Services\AspirationService;
use App\Services\TurnstileVerifier;
use App\Support\TrackingCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Aspirasi publik (tanpa login). Kode tracking hanya dibawa lewat session flash (pola POST → redirect),
 * tidak pernah muncul di URL. Halaman status TIDAK menampilkan catatan internal maupun nama pengurus.
 */
class AspirationController extends Controller
{
    public function create(TurnstileVerifier $turnstile): View
    {
        return view('public.aspirations.create', [
            'categories' => AspirationCategory::cases(),
            'siteKey' => $turnstile->siteKey(),
            'submittedCode' => session('submitted_code'),
        ]);
    }

    public function store(StoreAspirationRequest $request, AspirationService $service): RedirectResponse
    {
        $aspiration = $service->submit($request->safe()->only(['name', 'contact', 'is_anonymous', 'category', 'body']));

        return redirect()->route('public.aspirations.create')->with('submitted_code', $aspiration->tracking_code);
    }

    public function status(TurnstileVerifier $turnstile): View
    {
        $code = session('checked_code');
        $aspiration = $code
            ? Aspiration::query()->with('logs')->where('tracking_code', $code)->first()
            : null;

        return view('public.aspirations.status', [
            'siteKey' => $turnstile->siteKey(),
            'aspiration' => $aspiration,
        ]);
    }

    public function check(CheckAspirationStatusRequest $request): RedirectResponse
    {
        $code = $request->code();

        $exists = TrackingCode::isValidFormat($code, 'ASP')
            && Aspiration::query()->where('tracking_code', $code)->exists();

        if (! $exists) {
            return back()->withInput(['tracking_code' => $code])
                ->withErrors(['tracking_code' => 'Kode tracking tidak ditemukan. Periksa kembali kode Anda.']);
        }

        return redirect()->route('public.aspirations.status')->with('checked_code', $code);
    }
}
