@extends('layouts.identity')
@section('title', __('studio.count_spaces'))
@section('content')
<div class="bld-shell" data-building-library>
<header class="bld-heading"><div><span class="identity-kicker">{{ $organization->name }} / BUILDING STUDIO</span><h1>{{ __('building.library_title') }}</h1><p>{{ __('building.library_hint') }}</p></div><a class="identity-link" href="#new-building">+ {{ __('studio.add_building') }}</a></header>
<form method="GET" class="bld-library-filters identity-card">
<div class="identity-field"><label for="building-search">{{ __('building.search_buildings') }}</label><input id="building-search" type="search" name="q" value="{{ request('q') }}" maxlength="150"></div>
<div class="identity-field"><label for="building-property">{{ __('studio.properties') }}</label><select id="building-property" name="property"><option value="">{{ __('building.all_properties') }}</option>@foreach($properties as $p)<option value="{{ $p->id }}" @selected(request('property') == $p->id)>{{ $p->name }}</option>@endforeach</select></div>
<div class="identity-field"><label for="building-state">{{ __('building.state') }}</label><select id="building-state" name="state"><option value="">{{ __('building.all_states') }}</option>@foreach(['draft','active','archived'] as $s)<option value="{{ $s }}" @selected(request('state') === $s)>{{ __('building.state_'.$s) }}</option>@endforeach</select></div>
<button class="identity-button">{{ __('building.filter') }}</button><a class="identity-link" href="{{ route('app.spaces.index', $organization) }}">{{ __('building.clear_filters') }}</a>
</form>
@include('buildings.cards', ['allowDeletion' => true])
{{ $buildings->links('properties.pagination') }}
<section id="new-building" class="identity-card bld-new-building"><span class="identity-kicker">BUILDING STUDIO / +</span><h2>{{ __('studio.add_building') }}</h2><p>{{ __('building.choose_property') }}</p><div class="bld-property-actions">@foreach($properties as $p)@can('update', $p)<a class="identity-link" href="{{ route('app.declarations.new', [$organization,$p]) }}">+ {{ $p->name }} ↗</a>@endcan @endforeach</div>@if($properties->isEmpty())<a class="identity-link" href="{{ route('app.studio', $organization) }}">{{ __('building.create_property_first') }} ↗</a>@endif</section>
@if($unassigned->isNotEmpty())
<section class="organization-section"><h2>{{ __('building.unassigned_property_spaces') }}</h2><p>{{ __('building.unassigned_hint') }}</p>
@foreach($unassigned as $space)<article class="identity-card bld-unassigned" id="unassigned-{{ $space->uuid }}"><div><h3>{{ $space->name }}</h3><p>{{ $space->property->name }} · {{ __('building.type_'.$space->space_type) }}</p></div>@can('update', $space->property)
<form method="POST" action="{{ route('app.spaces.studio', [$organization, $space->property, $space]) }}">@csrf<div class="identity-field"><label for="assign-{{ $space->id }}">{{ __('building.choose_building') }}</label><select name="building_id" id="assign-{{ $space->id }}" required><option value="">{{ __('building.choose_building') }}</option>@foreach($targets->where('property_id', $space->property_id) as $target)<option value="{{ $target->id }}">{{ $target->name }}</option>@endforeach</select></div><button class="identity-text-button">{{ __('building.assign_open') }} ↗</button></form>@endcan</article>@endforeach
{{ $unassigned->links('properties.pagination') }}
</section>@endif
</div>
@endsection
