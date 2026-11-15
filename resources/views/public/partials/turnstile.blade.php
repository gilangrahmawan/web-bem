{{-- Widget Cloudflare Turnstile. $siteKey dari TurnstileVerifier::siteKey(). --}}
@if ($siteKey)
    <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-language="id" data-theme="light"></div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@else
    <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
        Verifikasi keamanan belum dikonfigurasi, sehingga formulir ini belum bisa dikirim. Hubungi BEM melalui halaman kontak.
    </p>
@endif
@error('cf-turnstile-response')
    <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>
@enderror
