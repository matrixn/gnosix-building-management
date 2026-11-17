@extends('layouts.identity')
@section('title', $building->display_name ?: $building->name)
@section('content')
<div class="bld-shell" data-building-dashboard @include('assets.building-context') @can('update', $property) @if($rooms) data-edit-url="{{ route('app.declarations.edit', [$organization,$property,$building->uuid]) }}" @endif @endcan>
    <header class="bld-heading">
        <div>
            <a class="identity-link" href="{{ route('app.properties.show', [$organization, $property]) }}">← {{ $property->name }}</a>
            <span class="identity-kicker">{{ $building->reference }}</span>
            <h1>{{ $building->display_name ?: $building->name }}</h1>
            <p>{{ $building->classification ? __('building.preset_'.$building->classification) : __('building.unknown') }} · {{ __('building.state_'.$building->state) }}</p>
        </div>
        <div class="bld-dashboard-actions">
@if(app(\App\Policies\MaintenancePolicy::class)->report(auth('web')->user(),$property))<a class="identity-button" href="{{ route('app.maintenance.issue.create',[$organization,'property'=>$property->id,'building'=>$building->id]) }}">＋ {{ __('maintenance.report') }}</a><a class="identity-link" href="{{ route('app.maintenance.index',[$organization,'property'=>$property->id,'building'=>$building->id]) }}">{{ __('maintenance.center') }} ↗</a>@endif
@if(app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'evidence.upload') || app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'evidence.review'))<a class="identity-button" href="{{ route('app.evidence.show',[$organization,$property,$building->uuid]) }}">{{ __('evidence.memory') }} ↗</a>@endif
            @can('update', $property)
                @if($rooms)
                    <a class="identity-button" href="{{ route('app.declarations.edit', [$organization, $property, $building->uuid]) }}">{{ __('building.edit') }} ↗</a>
                @endif
            @endcan
            @if($rooms)
                <a class="identity-link" href="{{ route('app.declarations.export', [$organization, $property, $building->uuid]) }}">{{ __('building.export') }}</a>
            @endif
        </div>
@include('dna.link',['dnaProperty'=>$property,'dnaFocus'=>'building:'.$building->uuid])
    </header>
    @if(!$rooms)
        <p class="identity-notice warning">{{ __('building.rooms_disabled') }}</p>
    @endif
    <div class="bld-workbench">
        <aside class="bld-levels identity-card">
            <h2>{{ __('building.levels') }}</h2>
            <div data-level-list class="bld-level-list"></div>
        </aside>
        <div class="bld-scene-card identity-card">
            @include('buildings.scene')
        </div>
        <aside class="bld-inspector identity-card">
            <span class="identity-kicker">{{ __('building.inspector') }}</span>
            <div data-inspector></div>
        </aside>
    </div>
    <div class="bld-bottom-grid">
        <section class="identity-card bld-record">
            <span class="identity-kicker">{{ __('building.identity') }}</span>
            <h2>{{ __('building.persistent_identity') }}</h2>
            <dl>
                @foreach(['name', 'display_name', 'reference', 'classification', 'custom_classification', 'address', 'country', 'timezone', 'construction_year', 'latitude', 'longitude'] as $field)
                    <div><dt>{{ __('building.'.$field) }}</dt><dd>{{ $building->{$field} ?? __('building.unknown') }}</dd></div>
                @endforeach
                <div><dt>UUID</dt><dd>{{ $building->uuid }}</dd></div>
            </dl>
        </section>
        <section class="identity-card">
            <span class="identity-kicker">{{ __('building.provenance') }}</span>
            <h2>{{ __('building.declared_identity') }}</h2>
            <p>{{ __('building.provenance_hint') }}</p>
            <span class="bld-verification">{{ __('building.unverified') }}</span>
            <p class="field-help">{{ __('building.evidence_future') }}</p>
            <h3>{{ __('building.accommodation') }}</h3>
            <div class="bld-unit-list" data-unit-list><p>{{ __('building.no_units') }}</p></div>
        </section>
    </div>
    <section class="identity-card bld-history">
        <span class="identity-kicker">{{ __('building.history') }}</span>
        <h2>{{ __('building.history_title') }}</h2>
        <p>{{ __('building.history_hint') }}</p>
        @foreach($revisions as $revision)
            <article>
                <div>
                    <strong>{{ __('building.action_'.$revision->action) }}</strong>
                    <span>{{ $revision->created_at->timezone($building->timezone ?? $property->timezone)->format('Y-m-d H:i T') }} · {{ $revision->actor?->name ?? __('building.system') }} · {{ __('building.source_'.$revision->source_kind) }} · {{ __('building.entity_'.$revision->entity_type) }}</span>
                </div>
                @if($revision->entity_type === 'declaration' && $rooms)
                    @if($revision->has_after)<a class="identity-link" href="{{ route('app.declarations.revision', [$organization, $property, $building->uuid, $revision->id]) }}">{{ __('building.revision_export') }} ↗</a>@endif
                    @if($revision->has_before)
                        <a class="identity-link" href="{{ route('app.declarations.revision', [$organization, $property, $building->uuid, $revision->id, 'side' => 'before']) }}">{{ __('building.before_export') }} ↗</a>
                    @endif
                @endif
            </article>
        @endforeach
        @include('properties.pagination', ['paginator' => $revisions])
    </section>
    <script type="application/json" data-building-data>{!! json_encode(['state' => $state, 'config' => config('building-studio'), 'strings' => __('building')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</div>
@endsection
