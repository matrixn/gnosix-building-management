@extends('layouts.identity')
@section('title', __('trust.library'))
@section('content')
<div class="trust-shell">
    <header class="trust-hero">
        <span class="identity-kicker">{{ $organization->name }}</span>
        <h1>{{ __('trust.library') }}</h1>
        <p>{{ __('trust.library_hint') }}</p>
    </header>
    <div class="trust-library">
        @forelse($buildings as $building)
            <a class="identity-card trust-building" href="{{ route('app.trust.show', [$organization, $building->property, $building->uuid]) }}">
                <span class="identity-kicker">{{ $building->reference }}</span>
                <strong>{{ $building->display_name ?: $building->name }}</strong>
                <span>{{ $building->property->name }} <span aria-hidden="true">↗</span></span>
            </a>
        @empty
            <p>{{ __('trust.empty') }}</p>
        @endforelse
    </div>
    {{ $buildings->links() }}
</div>
@endsection
