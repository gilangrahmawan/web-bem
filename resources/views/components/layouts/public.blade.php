@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'canonical' => null,
    'noindex' => false,
])
@php
    $siteName = 'BEM UBHINUS';
    $fullTitle = $title ? "{$title} — {$siteName}" : "{$siteName} — Badan Eksekutif Mahasiswa Universitas Bhinneka Nusantara";
    $metaDescription = \Illuminate\Support\Str::limit(
        $description ?: 'Website resmi Badan Eksekutif Mahasiswa Universitas Bhinneka Nusantara (UBHINUS) Malang: berita, kajian, program kerja, layanan aspirasi, dan pengajuan proposal.',
        160,
    );
    $metaImage = $image ?: asset('images/og-default.png');
    $canonicalUrl = $canonical ?: url()->current();
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?: $siteName }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?: $siteName }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    {{ $head ?? '' }}

    <meta name="theme-color" content="#232361">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-brand-accent focus:px-4 focus:py-2 focus:font-semibold focus:text-brand-primary">
        Lewati ke konten
    </a>

    @include('public.partials.header')

    <main id="konten" class="flex-1">
        {{ $slot }}
    </main>

    @include('public.partials.footer')
</body>
</html>
