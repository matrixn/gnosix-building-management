@extends('layouts.identity')
@section('title', __('studio.properties'))
@section('content')
<div class="bld-shell"><header class="bld-heading"><div><span class="identity-kicker">{{ $organization->name }} / GNOSIX</span><h1>{{ __('building.properties_title') }}</h1><p>{{ __('building.properties_hint') }}</p></div></header>
@can('update', $organization)<div class="organization-actions"><a class="identity-button" href="{{ route('app.studio', $organization) }}">Workspace Studio ↗</a><a class="identity-link" href="{{ route('app.workspace.settings', $organization) }}">{{ __('studio.organization_settings') }}</a></div>@endcan
<div class="bld-library-grid property-library" data-property-library>@forelse($properties as $property)<a class="identity-card bld-library-card property-library-card" href="{{ route('app.properties.show', [$organization, $property]) }}">
<div class="bld-card-top"><span class="identity-kicker">{{ __('studio.type_'.$property->property_type) }}</span><span class="bld-card-state">{{ trans_choice('building.property_buildings', $property->buildings_count, ['count' => $property->buildings_count]) }}</span></div>
<img class="property-illustration" src="{{ asset('illustrations/properties/'.config('workspace.property_visuals.'.$property->property_type, 'other').'.svg') }}" alt="" width="640" height="360" loading="lazy" decoding="async">
<h2>{{ $property->name }}</h2>@if($property->address)<p class="property-address">{{ $property->address }}</p>@endif
<div class="bld-card-counts"><span>{{ $property->country }}</span><span>{{ $property->timezone }}</span></div><strong class="bld-card-cta">{{ __('studio.open') }} ↗</strong>
</a>@empty<div class="identity-card bld-library-empty"><h2>{{ __('studio.no_properties') }}</h2><p>{{ __('building.properties_empty_hint') }}</p></div>@endforelse</div>
{{ $properties->links('properties.pagination') }}
@can('update', $organization)<details class="identity-card studio-manual"><summary>{{ __('studio.manual_property') }}</summary><form method="POST" action="{{ route('app.properties.store', $organization) }}" class="identity-form">@csrf @include('properties.property-fields', ['property' => null])<x-identity.button>{{ __('studio.add') }}</x-identity.button></form></details>@endcan
</div>
@endsection
