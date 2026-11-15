@extends('layouts.site')
@section('title', __('Pagina nu a fost găsită — GNOSIX'))
@section('content')
<main id="main-content" class="wrap legal-page"><span class="eyebrow">GNOSIX BUILDING MANAGEMENT</span><h1>{{ __('Această pagină') }}<br>{{ __('nu este aici.') }}</h1><p>{{ __('Poți continua de la pagina principală.') }}</p><a class="button" href="{{ \App\Support\PublicSite::home() }}">{{ __('Descoperă GNOSIX') }} <span>↗</span></a></main>
@endsection
