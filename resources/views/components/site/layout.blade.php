@props(['seo', 'isPreview' => false])
@php
    /** @var array{title: string, description: string, canonical: string, image: ?string, type: string, noindex: bool, published_time: ?string, json_ld: ?array} $seo */
@endphp
<!DOCTYPE html>
<html lang="sr-Latn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    @if ($seo['noindex'])
        <meta name="robots" content="noindex, follow">
    @endif

    <meta property="og:site_name" content="{{ \App\Support\Seo::SITE_NAME }}">
    <meta property="og:locale" content="sr_RS">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    @if ($seo['image'])
        <meta property="og:image" content="{{ $seo['image'] }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @if ($seo['published_time'])
        <meta property="article:published_time" content="{{ $seo['published_time'] }}">
    @endif
    <link rel="alternate" type="application/rss+xml" title="{{ \App\Support\Seo::SITE_NAME }} – Vijesti" href="{{ \App\Support\Seo::url('/feed/') }}">

    @if ($seo['json_ld'])
        <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Caveat:wght@600&display=swap" rel="stylesheet">

    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body>
    @include('site.partials.icons')

    @if ($isPreview)
        <div class="preview-bar">Pregled – ovo još nije objavljeno i posjetioci ga ne vide.</div>
    @endif

    @include('site.partials.header')

    <main id="sadrzaj">
        {{ $slot }}
    </main>

    @include('site.partials.footer')

    <a href="#top" class="to-top" id="toTop" aria-label="Na vrh"><svg><use href="#i-up"/></svg></a>

    <div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Fotografija">
        <button class="lb-btn lb-close" id="lbClose" type="button" aria-label="Zatvori"><svg><use href="#i-close"/></svg></button>
        <button class="lb-btn lb-prev" id="lbPrev" type="button" aria-label="Prethodna"><svg><use href="#i-left"/></svg></button>
        <img id="lbImg" src="data:," alt="">
        <button class="lb-btn lb-next" id="lbNext" type="button" aria-label="Sljedeća"><svg><use href="#i-right"/></svg></button>
        <div class="cap" id="lbCap"></div>
    </div>
</body>
</html>
