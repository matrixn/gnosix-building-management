@extends('layouts.site')
@section('title', 'Reîncarcă formularul — GNOSIX')
@section('content')
<main id="main-content" class="wrap legal-page"><span class="eyebrow">GNOSIX BUILDING MANAGEMENT</span><h1>Reluăm de aici.</h1><p>Sesiunea formularului a expirat. Reîncarcă pagina înainte de a trimite o nouă cerere.</p><a class="button" href="{{ route('home') }}#pilot">Reîncarcă pagina <span>↗</span></a></main>
@endsection
