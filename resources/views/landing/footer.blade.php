<footer class="wrap footer">
    <a class="brand" href="{{ \App\Support\PublicSite::home() }}" aria-label="{{ __('GNOSIX — pagina principală') }}"><span class="brand-symbol">G<span>×</span></span><span>GNOSIX<small>BUILDING MANAGEMENT</small></span></a>
    <p>{{ __('Memoria clădirii. Claritatea deciziilor.') }}</p>
    <a href="{{ \App\Support\PublicSite::privacy() }}">{{ __('Confidențialitate') }}</a>
    <a href="{{ route('login', ['lang' => app()->getLocale()]) }}">{{ __('Contul meu') }}</a>
    <button class="motion-control" data-motion data-pause="{{ __('Pauză animații') }}" data-resume="{{ __('Reia animațiile') }}" aria-pressed="false">{{ __('Pauză animații') }}</button>
    <small>© {{ date('Y') }} GNOSIX Building Management · {{ __('Produs în dezvoltare · Program parteneri pilot') }}</small>
</footer>
