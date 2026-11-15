@php
    $locale = \App\Support\PublicSite::locale();
    $privacy = request()->routeIs('privacy', 'privacy.en');
    $publicPage = request()->routeIs('home', 'home.en', 'privacy', 'privacy.en');
    $title = $privacy ? __('Confidențialitate — GNOSIX Building Management') : __('GNOSIX Building Management — Memoria digitală a clădirilor');
    $description = $privacy ? __('Cum folosim datele din formularul de contact GNOSIX: scop, livrare prin email și drepturile tale.') : __('Documente, echipamente și istoric tehnic, în contextul aceleiași clădiri. Descoperă GNOSIX și propune o colaborare în programul pilot.');
    $canonical = \App\Support\PublicSite::canonical();
    $schema = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Organization', '@id' => \App\Support\SiteSettings::value('canonical_url').'/#organization', 'name' => 'GNOSIX Building Management', 'url' => \App\Support\SiteSettings::value('canonical_url').'/', 'logo' => \App\Support\SiteSettings::value('canonical_url').'/favicon.svg'],
        ['@type' => 'WebSite', '@id' => \App\Support\SiteSettings::value('canonical_url').'/#website', 'name' => 'GNOSIX Building Management', 'url' => \App\Support\SiteSettings::value('canonical_url').'/', 'inLanguage' => ['ro', 'en'], 'publisher' => ['@id' => \App\Support\SiteSettings::value('canonical_url').'/#organization']],
        ['@type' => 'WebPage', '@id' => $canonical.'#webpage', 'url' => $canonical, 'name' => $title, 'description' => $description, 'inLanguage' => $locale, 'isPartOf' => ['@id' => \App\Support\SiteSettings::value('canonical_url').'/#website']],
    ]];
@endphp
<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $title)</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ \App\Support\SiteSettings::value('indexable') && $publicPage ? 'index,follow,max-image-preview:large' : 'noindex,nofollow' }}">
    @if ($publicPage)
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="ro" href="{{ \App\Support\PublicSite::canonical('ro') }}">
    <link rel="alternate" hreflang="en" href="{{ \App\Support\PublicSite::canonical('en') }}">
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\PublicSite::canonical('ro') }}">
    @endif
    <meta name="theme-color" content="#0b171e">
    <meta property="og:type" content="website"><meta property="og:site_name" content="GNOSIX Building Management">
    <meta property="og:locale" content="{{ $locale === 'en' ? 'en_GB' : 'ro_RO' }}"><meta property="og:locale:alternate" content="{{ $locale === 'en' ? 'ro_RO' : 'en_GB' }}">
    <meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ \App\Support\SiteSettings::value('canonical_url') }}/images/{{ $locale === 'en' ? 'gnosix-social-en.png' : 'gnosix-social.png' }}">
    <meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta property="og:image:alt" content="{{ __('GNOSIX — Memoria digitală a clădirilor') }}">
    <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $title }}"><meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ \App\Support\SiteSettings::value('canonical_url') }}/images/{{ $locale === 'en' ? 'gnosix-social-en.png' : 'gnosix-social.png' }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if ($publicPage)<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="atlas">
    <a class="skip-link" href="#main-content">{{ __('Sari la conținut') }}</a>
    <noscript><div class="wrap no-js-contact">{{ __('Animațiile sunt oprite. Formularul de contact funcționează și fără JavaScript.') }}</div></noscript>
    @yield('content')
</body>
</html>
