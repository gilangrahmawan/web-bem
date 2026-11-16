{{-- Render PDF dokumen Drive (dompdf). $content sudah disanitasi (profil purifier 'document'). --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 2cm 2cm 2cm 2.5cm; }
        body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 12pt; line-height: 1.5; color: #111; }
        .kop { width: 100%; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 18px; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 72px; }
        .kop .logo img { width: 64px; height: 64px; }
        .kop .text { text-align: center; }
        .kop .t1 { font-size: 15pt; font-weight: bold; letter-spacing: .5px; }
        .kop .t2 { font-size: 13pt; font-weight: bold; }
        .kop .t3 { font-size: 11pt; font-style: italic; }
        .kop .t4 { font-size: 9pt; }
        h1.doc-title { font-size: 14pt; text-align: center; margin: 0 0 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #555; padding: 4px 6px; }
        .kop td { border: 0; }
        blockquote { margin-left: 1cm; color: #333; }
    </style>
</head>
<body>
    @if ($letterhead)
        <table class="kop">
            <tr>
                <td class="logo">@if ($letterhead['logo_data'])<img src="{{ $letterhead['logo_data'] }}" alt="Logo BEM">@endif</td>
                <td class="text">
                    <div class="t1">{{ $letterhead['title'] }}</div>
                    <div class="t2">{{ $letterhead['subtitle'] }}</div>
                    @if ($letterhead['cabinet'])<div class="t3">{{ $letterhead['cabinet'] }}</div>@endif
                    @if ($letterhead['contact'])<div class="t4">{{ $letterhead['contact'] }}</div>@endif
                </td>
                <td class="logo"></td>
            </tr>
        </table>
    @endif

    <h1 class="doc-title">{{ $document->title }}</h1>
    {!! $content !!}
</body>
</html>
