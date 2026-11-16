<?php

namespace App\Http\Requests\Public;

use App\Models\Recruitment;
use App\Rules\ValidTurnstile;
use App\Services\RecruitmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Form pendaftaran oprec publik (Turnstile + rate limit 5/IP/jam di route). */
class StoreRecruitmentApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $open = array_map('intval', Recruitment::currentlyOpen()?->open_divisions ?? []);

        return [
            'name' => ['required', 'string', 'max:100'],
            'nim' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9.\-]{5,20}$/'],
            'prodi' => ['required', 'string', 'max:100'],
            'batch_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'first_choice_division_id' => ['required', 'integer', Rule::in($open)],
            'second_choice_division_id' => ['nullable', 'integer', Rule::in($open), 'different:first_choice_division_id'],
            'motivation' => ['required', 'string', 'min:30', 'max:3000'],
            'experience' => ['nullable', 'string', 'max:3000'],
            'cv' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:2048'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'consent' => ['accepted'],
            'cf-turnstile-response' => ['required', 'string', new ValidTurnstile],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'nim' => 'NIM',
            'prodi' => 'program studi',
            'batch_year' => 'angkatan',
            'phone' => 'nomor WhatsApp',
            'email' => 'email',
            'first_choice_division_id' => 'pilihan divisi 1',
            'second_choice_division_id' => 'pilihan divisi 2',
            'motivation' => 'motivasi',
            'experience' => 'pengalaman organisasi',
            'cv' => 'CV',
            'photo' => 'foto',
            'consent' => 'persetujuan',
            'cf-turnstile-response' => 'verifikasi keamanan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nim.regex' => 'Format NIM tidak valid (hanya huruf, angka, titik, atau tanda hubung).',
            'phone.regex' => 'Nomor WhatsApp harus diawali 08, 628, atau +628 dan hanya berisi angka.',
            'first_choice_division_id.in' => 'Pilih divisi yang dibuka pada gelombang ini.',
            'second_choice_division_id.in' => 'Pilih divisi yang dibuka pada gelombang ini.',
            'second_choice_division_id.different' => 'Pilihan 2 harus berbeda dari pilihan 1.',
            'consent.accepted' => 'Anda harus menyetujui pernyataan penggunaan data untuk mendaftar.',
            'cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nim' => RecruitmentService::normalizeNim((string) $this->input('nim')),
            'phone' => preg_replace('/[\s\-().]/', '', (string) $this->input('phone')),
            'second_choice_division_id' => $this->filled('second_choice_division_id') ? $this->input('second_choice_division_id') : null,
        ]);
    }
}
