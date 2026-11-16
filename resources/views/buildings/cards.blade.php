<div class="bld-library-grid">
@forelse($buildings as $building)
@php($ownerProperty = $building->property)
@php($editable = auth('web')->user()->can('update', $ownerProperty) && ($canSpaces ?? true))
<article class="bld-library-item"><a class="bld-library-card identity-card" href="{{ route($editable ? 'app.declarations.edit' : 'app.declarations.show', [$organization, $ownerProperty, $building->uuid]) }}">
    <div class="bld-card-top"><span class="identity-kicker">{{ $ownerProperty->name }}</span><span class="bld-card-state">{{ __('building.state_'.$building->state) }}</span></div>
    <div class="bld-card-art" aria-hidden="true">@for($plane = 0; $plane < max(1, min(6, $building->floors_count)); $plane++)<i style="--plane:{{ $plane }}"></i>@endfor<span>GNX / {{ $building->floors_count }}</span></div>
    <h3>{{ $building->display_name ?: $building->name }}</h3><p>{{ $building->reference }}</p>
    <div class="bld-card-counts"><span>{{ $building->floors_count }} {{ __('building.levels') }}</span>@if($canSpaces ?? true)<span>{{ $building->spaces_count }} {{ __('building.spaces') }}</span>@endif</div>
    <strong class="bld-card-cta">{{ $editable ? __('building.open_studio') : __('building.view_building') }} ↗</strong>
</a>
@if(($allowDeletion ?? false) && $editable)<footer><a class="bld-danger" href="{{ route('app.declarations.delete', [$organization,$ownerProperty,$building->uuid]) }}">{{ __('building.delete_building') }} <span class="identity-sr-only">{{ $building->name }}</span></a></footer>@endif
</article>
@empty
<div class="identity-card bld-library-empty"><h2>{{ __('building.no_buildings') }}</h2><p>{{ __('building.no_buildings_hint') }}</p></div>
@endforelse
</div>
