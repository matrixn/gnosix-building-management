@if(request()->is('app', 'app/*'))
@include('identity.error', ['errorCode' => 500])
@else
@extends('layouts.site')
@section('content')
<main id="main-content" class="wrap legal-page"><h1>GNOSIX / 500</h1><a class="button" href="{{ \App\Support\PublicSite::home() }}">GNOSIX ↗</a></main>
@endsection
@endif
