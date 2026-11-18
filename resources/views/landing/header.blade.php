<header class="site-header wrap" id="top">
    <a class="brand" href="{{ \App\Support\PublicSite::home() }}" aria-label="{{ __('GNOSIX — pagina principală') }}"><span class="brand-symbol">G<span>×</span></span><span>GNOSIX<small>BUILDING MANAGEMENT</small></span></a>
    <nav aria-label="{{ __('Navigare principală') }}"><a href="{{ \App\Support\PublicSite::home() }}#concept">{{ __('Platforma') }}</a><a href="{{ \App\Support\PublicSite::home() }}#intelligence">{{ __('Experiența') }}</a><a href="{{ \App\Support\PublicSite::home() }}#pentru-cine">{{ __('Pentru cine') }}</a><a href="{{ \App\Support\PublicSite::home() }}#contact">{{ __('Contact') }}</a></nav>
    <div class="language-switch" role="group" aria-label="{{ __('Limba paginii') }}">
        @foreach (['ro' => 'Română', 'en' => 'Engleză'] as $locale => $label)
        <a href="{{ request()->routeIs('privacy', 'privacy.en') ? \App\Support\PublicSite::privacy($locale) : route('home.'.$locale, $locale === 'en' ? ['lang' => 'en'] : []) }}" hreflang="{{ $locale }}" lang="{{ $locale }}" aria-label="{{ __($label) }}" @if (app()->getLocale() === $locale) aria-current="page" @endif>{{ strtoupper($locale) }}</a>
        @endforeach
    </div>
    <a class="button small" href="{{ \App\Support\PublicSite::home() }}#contact">{{ __('Hai să discutăm') }} <span aria-hidden="true">↗</span></a>
    <button class="menu-toggle" aria-label="{{ __('Deschide meniul') }}" aria-expanded="false" data-menu-open="{{ __('Deschide meniul') }}" data-menu-close="{{ __('Închide meniul') }}">☰</button>
</header>
