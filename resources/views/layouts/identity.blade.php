@php
    $shell = request()->routeIs('app.dashboard', 'app.account', 'app.organizations.index', 'app.organizations.show', 'app.organizations.module');
    $activeOrganization = app(\App\Support\TenantContext::class)->current();
    $profileRegistry = app(\App\Support\ProfileRegistry::class);
    $member = auth('web')->user();
    $pageTitle = trim($__env->yieldContent('title')) ?: __('identity.sign_in');
    $currentRoute = request()->route()?->getName();
    $localeRoute = request()->isMethod('GET') && $currentRoute && ! request()->routeIs('verification.verify') ? $currentRoute : 'login';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><meta name="theme-color" content="#101225">
    <title>{{ $pageTitle }} — GNOSIX</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/identity.css', 'resources/js/identity.js'])
</head>
<body class="identity {{ $shell ? 'workspace-layout' : '' }}">
<a class="identity-skip" href="#identity-main">{{ __('identity.skip_content') }}</a>
@if($shell)
<aside class="workspace-sidebar"><x-identity.logo/>
    <div class="workspace-scope"><span class="identity-kicker">{{ $activeOrganization ? __('workspace.profile_'.$activeOrganization->profile?->profile_key) : 'GNOSIX' }}</span><p class="active-organization-name">{{ $activeOrganization?->name ?? __('workspace.no_active_organization') }}</p><a class="identity-link" href="{{ route('app.organizations.index') }}">{{ __('workspace.switch') }} ↗</a></div>
    <nav aria-label="{{ __('identity.app_navigation') }}">@foreach($profileRegistry->navigation() as $item)<a href="{{ route($item['route']) }}" @if(request()->routeIs($item['route'])) aria-current="page" @endif><span aria-hidden="true">{{ $item['icon'] }}</span>{{ __($item['label']) }}</a>@endforeach</nav>
    @if($activeOrganization)<nav class="capability-navigation" aria-label="{{ __('workspace.capabilities') }}">@foreach(app(\App\Support\CapabilityService::class)->forOrganization($member, $activeOrganization) as $key => $definition)@if($definition['allowed'])<a href="{{ route('app.organizations.module', [$activeOrganization, $key]) }}"><span aria-hidden="true">{{ $definition['icon'] }}</span>{{ __('workspace.cap_'.str_replace('.', '_', $key)) }}</a>@endif
@endforeach</nav>@endif
    <p class="workspace-note">{{ __('workspace.tenant_boundary') }}</p></aside>
@endif
<div class="identity-frame">
    <header class="identity-topbar">
        @unless($shell)<x-identity.logo/>@else<span class="identity-breadcrumb">GNOSIX / {{ $pageTitle }}</span>@endunless
        <div class="identity-top-actions">
            <nav class="identity-language" aria-label="{{ __('identity.language') }}">
                @foreach(['ro', 'en'] as $language)
                <a href="{{ route($localeRoute, array_merge($localeRoute === $currentRoute ? request()->route()->parameters() : [], ['lang' => $language], request()->routeIs('password.reset') ? ['email' => request()->query('email', '')] : [])) }}" lang="{{ $language }}" @if(app()->getLocale() === $language) aria-current="page" @endif aria-label="{{ $language === 'ro' ? 'Română' : 'English' }}">{{ strtoupper($language) }}</a>
                @endforeach
            </nav>
            @auth('web')<form method="POST" action="{{ route('logout') }}">@csrf<button class="identity-text-button">{{ __('identity.sign_out') }} <span aria-hidden="true">↗</span></button></form>@endauth
        </div>
    </header>
    <main id="identity-main" class="{{ $shell ? 'workspace-main' : 'identity-main' }}">
        @if(session('status'))<div class="identity-notice {{ session('delivery_warning') ? 'warning' : '' }}" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="identity-notice error" role="alert" tabindex="-1" data-validation-summary><strong>{{ __('identity.review_errors') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="identity-footer"><span>© {{ date('Y') }} GNOSIX</span><a href="{{ \App\Support\PublicSite::privacy(app()->getLocale()) }}">{{ __('identity.privacy') }}</a><span>{{ __('identity.building_memory') }}</span></footer>
</div>
</body></html>
