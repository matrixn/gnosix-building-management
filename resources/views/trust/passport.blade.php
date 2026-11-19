@extends('layouts.identity')
@section('title', __('trust.title'))
@section('content')
<div class="trust-shell">
    <a class="identity-link" href="{{ route('app.declarations.show', [$organization, $property, $building->uuid]) }}">← {{ $building->name }}</a>
    @include('trust.hero')
    <div class="trust-actions">
        <a class="identity-link" href="{{ route('app.trust.show', [$organization, $property, $building->uuid]) }}">{{ __('trust.refresh') }}</a>
        @include('trust.exports', ['exportRoute' => 'app.trust.export', 'exportParams' => [$organization, $property, $building->uuid]])
    </div>
    @include('trust.packet', ['internal' => true])
    @if($canManage)
        <section class="identity-card trust-panel" aria-labelledby="handover-drafts">
            <h2 id="handover-drafts">{{ __('trust.studio') }}</h2>
            <form method="post" action="{{ route('app.trust.draft', [$organization, $property, $building->uuid]) }}">
                @csrf
                <button class="identity-button">{{ __('trust.new') }}</button>
            </form>
            <ul class="trust-list">
                @foreach($flows as $flow)
                    <li><a class="identity-link" href="{{ route('app.trust.flow', [$organization, $property, $building->uuid, $flow->id]) }}">{{ __('trust.state_'.$flow->state) }} · <time datetime="{{ $flow->created_at }}">{{ $flow->created_at }}</time> ↗</a></li>
                @endforeach
            </ul>
            {{ $flows->links() }}
        </section>
    @endif
</div>
@endsection
