@extends('layouts.identity')
@section('title', __('building.trash'))
@section('content')
<div class="bld-shell"><header class="bld-heading"><div><span class="identity-kicker">{{ $organization->name }} / GNOSIX</span><h1>{{ __('building.trash') }}</h1><p>{{ __('building.trash_hint') }}</p></div></header>
<nav class="bld-library-tabs" aria-label="{{ __('building.library_navigation') }}"><a class="identity-link" href="{{ route('app.spaces.index', $organization) }}">← {{ __('building.current_buildings') }}</a><a class="identity-link" aria-current="page" href="{{ route('app.spaces.trash', $organization) }}">{{ __('building.trash') }}</a></nav>
<div class="bld-trash-list">@forelse($buildings as $building)<article class="identity-card bld-trash-item"><div><span class="identity-kicker">{{ $building->property->name }}</span><h2>{{ $building->display_name ?: $building->name }}</h2><p>{{ $building->reference }}</p><p>{{ __('building.deleted_on') }} <time datetime="{{ $building->deleted_at->toISOString() }}">{{ $building->deleted_at->timezone($building->property->timezone ?? $organization->timezone)->format('Y-m-d H:i T') }}</time></p></div>
@can('update', $building->property)<form method="POST" action="{{ route('app.declarations.restore', [$organization,$building->property,$building->uuid]) }}" data-submit-form>@csrf<input type="hidden" name="version" value="{{ $building->declaration_version }}"><button class="identity-button" data-loading-label="{{ __('building.restoring') }}"><span>{{ __('building.restore_building') }}</span><span class="identity-sr-only">{{ $building->name }}</span></button></form>@endcan
</article>@empty<div class="identity-card"><h2>{{ __('building.trash_empty') }}</h2><p>{{ __('building.trash_empty_hint') }}</p></div>@endforelse</div>
{{ $buildings->links('properties.pagination') }}</div>
@endsection
