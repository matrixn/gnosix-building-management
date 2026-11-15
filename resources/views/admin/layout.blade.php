<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administrare platformă') — GNOSIX</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body>
@auth('platform_admin')
<div class="admin-shell">
    <aside class="sidebar">
        <a class="wordmark" href="{{ route('admin.settings') }}"><span>G×</span> GNOSIX</a>
        <span class="scope">ADMINISTRAREA PLATFORMEI</span>
        <p class="sidebar-label">CONFIGURARE</p>
        <a class="sidebar-link active" href="{{ route('admin.settings') }}">⚙ <span>Setări platformă</span><b>↗</b></a>
        <div class="sidebar-note"><span class="dot"></span> Acces intern GNOSIX<p>Această zonă este separată de spațiul de administrare al clienților.</p></div>
        <a class="back-link" href="{{ route('home') }}">← Vezi pagina publică</a>
    </aside>
    <div class="admin-main">
        <header class="admin-header"><span>GNOSIX / Platformă / Setări</span><div><span class="admin-identity">{{ auth('platform_admin')->user()->email }}</span><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="button ghost small">Ieșire ↗</button></form></div></header>
        <main id="main-content" class="content">
@else
<main id="main-content" class="login-layout">
@endauth
    @if (session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="notice error" role="alert"><strong>Verifică informațiile.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
@auth('platform_admin')
        </main><footer class="admin-footer">GNOSIX Building Management <span>Administrare platformă · {{ date('Y') }}</span></footer>
    </div>
</div>
@else
</main>
@endauth
</body></html>
