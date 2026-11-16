@extends('layouts.identity')
@section('title', $property->name)
@section('content')
<div class="workspace-heading"><span class="identity-kicker">{{ $organization->name }} / {{ __('studio.type_'.$property->property_type) }}</span><h1>{{ $property->name }}</h1><a class="identity-link" href="{{ route('app.properties.index', $organization) }}">← {{ __('studio.properties') }}</a></div>
@can('update', $property)<details class="identity-card studio-manual"><summary>{{ __('studio.edit_property') }}</summary><form method="POST" action="{{ route('app.properties.update', [$organization, $property]) }}" class="identity-form">@csrf @method('PATCH')@include('properties.property-fields')<x-identity.button>{{ __('studio.save') }}</x-identity.button></form></details>@endcan
@can('update', $property)<a class="identity-button auth-back" href="{{ route('app.declarations.new', [$organization,$property]) }}">Building Studio ↗</a>@endcan
<section class="organization-section"><h2>{{ __('studio.count_buildings') }}</h2><p class="identity-caption">{{ __('building.library_hint') }}</p>
@include('buildings.cards', ['buildings' => $property->buildings])</section>
@if($canSpaces)<section class="organization-section"><a class="identity-link" href="{{ route('app.spaces.index', [$organization, 'property' => $property->id]) }}">{{ __('building.library_title') }} ↗</a></section>@endif
@endsection
