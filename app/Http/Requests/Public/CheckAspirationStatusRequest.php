<?php

namespace App\Http\Requests\Public;

use App\Rules\ValidTurnstile;
use App\Support\TrackingCode;
use Illuminate\Foundation\Http\FormRequest;

/** Cek status aspirasi dengan kode tracking (Turnstile + rate limit 20/IP/jam di route). */
class CheckAspirationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tracking_code' => ['required', 'string', 'max:20'],
            'cf-turnstile-response' => ['required', 'string', new ValidTurnstile],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['tracking_code' => 'kode tracking', 'cf-turnstile-response' => 'verifikasi keamanan'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.'];
    }

    public function code(): string
    {
        return TrackingCode::normalize($this->input('tracking_code'));
    }
}
