@extends('layouts.site')
@section('title', __('Reîncarcă formularul — GNOSIX'))
@section('content')
<main id="main-content" class="wrap legal-page"><span class="eyebrow">GNOSIX BUILDING MANAGEMENT</span><h1>{{ __('Reluăm de aici.') }}</h1><p>{{ __('Sesiunea formularului a expirat. Reîncarcă pagina înainte de a trimite o nouă cerere.') }}</p><a class="button" href="{{ \App\Support\PublicSite::home() }}#contact">{{ __('Reîncarcă pagina') }} <span>↗</span></a></main>
@endsection
