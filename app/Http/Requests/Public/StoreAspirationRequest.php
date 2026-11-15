<?php

namespace App\Http\Requests\Public;

use App\Enums\AspirationCategory;
use App\Rules\ValidTurnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Form aspirasi publik (Turnstile + rate limit di route). */
class StoreAspirationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_anonymous' => ['boolean'],
            'name' => ['nullable', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:100'],
            'category' => ['required', Rule::enum(AspirationCategory::class)],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
            'cf-turnstile-response' => ['required', 'string', new ValidTurnstile],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'contact' => 'kontak',
            'category' => 'kategori',
            'body' => 'isi aspirasi',
            'cf-turnstile-response' => 'verifikasi keamanan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_anonymous' => $this->boolean('is_anonymous')]);
    }
}
