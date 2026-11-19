@extends('layouts.identity')
@section('title', __('trust.portal'))
@section('content')
<div class="trust-shell">
    <header class="trust-hero"><div><h1>{{ __('trust.portal') }}</h1><p>{{ __('trust.no_access') }}</p></div></header>
    <div class="trust-library">
        @forelse($packages as [$flow, $building])
            <a class="identity-card trust-building" href="{{ route('handover.show', $flow->id) }}">
                <span class="identity-kicker">{{ __('trust.state_'.$flow->state) }}</span>
                <strong>{{ $building->name }}</strong>
                <span>{{ $building->reference }} <span aria-hidden="true">↗</span></span>
            </a>
        @empty
            <p>{{ __('trust.empty') }}</p>
        @endforelse
    </div>
    {{ $flows->links() }}
</div>
@endsection
