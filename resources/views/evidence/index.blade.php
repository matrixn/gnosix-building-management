@extends('layouts.identity')
@section('title', __('evidence.memory'))
@section('content')
<div class="bld-shell"><header class="bld-heading"><div><span class="identity-kicker">GNOSIX / EVIDENCE ENGINE</span><h1>{{ __('evidence.memory') }}</h1><p>{{ __('evidence.index_hint') }}</p></div></header>
<div class="bld-library-grid">@forelse($buildings as $building)<a class="identity-card bld-library-card" href="{{ route('app.evidence.show',[$organization,$building->property,$building->uuid]) }}"><span class="identity-kicker">{{ $building->property->name }}</span><h2>{{ $building->name }}</h2><p>{{ $building->reference }}</p><strong class="bld-card-cta">{{ __('evidence.open_memory') }} ↗</strong></a>@empty<p>{{ __('building.no_buildings') }}</p>@endforelse</div>{{ $buildings->links('properties.pagination') }}</div>
@endsection
