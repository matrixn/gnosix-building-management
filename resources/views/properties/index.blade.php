@extends('layouts.identity')
@section('title', __('studio.properties'))
@section('content')
<div class="workspace-heading"><span class="identity-kicker">{{ $organization->name }}</span><h1>{{ __('studio.properties') }}</h1><p>{{ __('studio.hierarchy_copy') }}</p></div>
@can('update', $organization)<div class="organization-actions"><a class="identity-button" href="{{ route('app.studio', $organization) }}">Workspace Studio ↗</a><a class="identity-link" href="{{ route('app.workspace.settings', $organization) }}">{{ __('studio.organization_settings') }}</a></div>@endcan
<div class="organization-list">@forelse($properties as $property)<article class="identity-card"><span class="identity-kicker">{{ __('studio.type_'.$property->property_type) }}</span><h2>{{ $property->name }}</h2><p>{{ $property->buildings_count }} {{ __('studio.count_buildings') }} · {{ $property->country }} · {{ $property->timezone }}</p><a class="identity-link" href="{{ route('app.properties.show', [$organization, $property]) }}">{{ __('studio.open') }} ↗</a></article>@empty<p>{{ __('studio.no_properties') }}</p>@endforelse</div>
{{ $properties->links('properties.pagination') }}
@can('update', $organization)<details class="identity-card studio-manual"><summary>{{ __('studio.manual_property') }}</summary><form method="POST" action="{{ route('app.properties.store', $organization) }}" class="identity-form">@csrf @include('properties.property-fields', ['property' => null])<x-identity.button>{{ __('studio.add') }}</x-identity.button></form></details>@endcan
@endsection
