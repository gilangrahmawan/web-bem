<?php

namespace App\Http\Requests\Public;

use App\Rules\ValidTurnstile;
use App\Services\RecruitmentService;
use App\Support\TrackingCode;
use Illuminate\Foundation\Http\FormRequest;

/** Cek hasil oprec dengan kode pendaftaran + NIM (Turnstile + rate limit 20/IP/jam di route). */
class CheckRecruitmentResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'registration_code' => ['required', 'string', 'max:20'],
            'nim' => ['required', 'string', 'max:20'],
            'cf-turnstile-response' => ['required', 'string', new ValidTurnstile],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['registration_code' => 'kode pendaftaran', 'nim' => 'NIM', 'cf-turnstile-response' => 'verifikasi keamanan'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.'];
    }

    public function code(): string
    {
        return TrackingCode::normalize($this->input('registration_code'));
    }

    public function nim(): string
    {
        return RecruitmentService::normalizeNim((string) $this->input('nim'));
    }
}
