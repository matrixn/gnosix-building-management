@if(request()->is('app', 'app/*'))
@include('identity.error', ['errorCode' => 429])
@else
@extends('layouts.site')
@section('title', __('Prea multe cereri — GNOSIX'))
@section('content')
<main id="main-content" class="wrap legal-page"><span class="eyebrow">GNOSIX BUILDING MANAGEMENT</span><h1>{{ __('O scurtă pauză.') }}</h1><p>{{ __('Ai trimis mai multe cereri într-un interval scurt. Te rugăm să aștepți înainte de o nouă încercare.') }}</p><a class="button" href="{{ \App\Support\PublicSite::home() }}">{{ __('Înapoi la GNOSIX') }} <span>↗</span></a></main>
@endsection

@endif
