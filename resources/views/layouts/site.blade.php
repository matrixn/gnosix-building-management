<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GNOSIX Building Management — Memoria digitală a clădirilor')</title>
    <meta name="description" content="Documente, echipamente și istoric tehnic, în contextul aceleiași clădiri. Descoperă GNOSIX și propune o colaborare în programul pilot.">
    <meta name="robots" content="{{ config('gnosix.indexable') ? 'index,follow' : 'noindex,nofollow' }}">
    <link rel="canonical" href="{{ config('gnosix.canonical_url').(request()->routeIs('privacy') ? '/confidentialitate' : '/') }}">
    <meta name="theme-color" content="#0b171e">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ro_RO">
    <meta property="og:site_name" content="GNOSIX Building Management">
    <meta property="og:title" content="GNOSIX — Clădirile au o viață. Dă-le o memorie.">
    <meta property="og:description" content="Memoria digitală a clădirilor. Construim alături de parteneri pilot.">
    <meta property="og:url" content="{{ config('gnosix.canonical_url').(request()->routeIs('privacy') ? '/confidentialitate' : '/') }}">
    <meta property="og:image" content="{{ config('gnosix.canonical_url') }}/images/gnosix-social.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="GNOSIX — Memoria digitală a clădirilor">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="atlas">
    <a class="skip-link" href="#main-content">Sari la conținut</a>
    <noscript><div class="wrap no-js-contact">Animațiile sunt oprite. Poți trimite o cerere din <a href="#pilot-dialog">formularul de la finalul paginii</a>.</div></noscript>
    @yield('content')
</body>
</html>
