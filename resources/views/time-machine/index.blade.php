@extends('layouts.identity')
@section('title', __('time.title'))
@section('content')
<main class="tm-shell">
    <header class="tm-header">
        <a class="identity-link" href="{{ route('app.organizations.show', $organization) }}">{{ $organization->name }}</a>
        <h1>{{ __('time.title') }}</h1>
        <p>{{ __('time.subtitle') }}</p>
    </header>
    <ul class="tm-building-list">
        @forelse($buildings as $building)
            <li><a href="{{ route('app.time-machine.show', [$organization, $building->property, $building->uuid]) }}"><strong>{{ $building->display_name ?: $building->name }}</strong><span>{{ $building->property->name }} @if($building->trashed() || $building->state === 'archived') · {{ __('time.archive') }} @endif</span></a></li>
        @empty
            <li>{{ __('time.empty') }}</li>
        @endforelse
    </ul>
    {{ $buildings->links() }}
</main>
@endsection
