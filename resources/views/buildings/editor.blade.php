@extends('layouts.identity')
@section('title','Building Studio')
@section('content')
<div class="bld-shell" data-building-studio data-draft-url="{{ route('app.declarations.draft',[$organization,$property,$draft->id]) }}" data-commit-url="{{ route('app.declarations.commit',[$organization,$property,$draft->id]) }}" data-focus-space="{{ request('space') }}" data-version="{{ $draft->version }}" data-stage-value="{{ $draft->stage }}">
<header class="bld-heading"><div><a class="identity-link" href="{{ route('app.properties.show',[$organization,$property]) }}">← {{ $property->name }}</a><a class="identity-link bld-library-back" href="{{ route('app.spaces.index', $organization) }}">{{ __('building.library_title') }} ↗</a><span class="identity-kicker">GNOSIX / BUILDING STUDIO</span><h1>{{ $draft->building_id ? $draft->state['identity']['name'] : __('building.headline') }}</h1><p>{{ __('building.intro') }}</p></div>@if($draft->building_id)@if(app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'evidence.upload') || app(\App\Support\CapabilityService::class)->allows(auth('web')->user(),$organization,'evidence.review'))<a class="identity-link" href="{{ route('app.evidence.show',[$organization,$property,\App\Models\Building::findOrFail($draft->building_id)->uuid]) }}">{{ __('evidence.memory') }} ↗</a>@endif
@endif
<span class="bld-live"><i></i>{{ __('building.live') }}</span></header>
<div class="bld-interactive">
<nav class="bld-stages" aria-label="{{ __('building.steps') }}">@foreach(['identity','structure','review'] as $key)<button type="button" data-stage="{{ $key }}">{{ __('building.step_'.$key) }}</button>@endforeach</nav>
<section class="bld-stage" data-panel="identity">
<div class="bld-section-heading"><span>01 / {{ __('building.preset') }}</span><h2>{{ __('building.start_shape') }}</h2><p>{{ __('building.preset_hint') }}</p></div>
<button type="button" class="identity-text-button" data-unknown-type>{{ __('building.unknown_type') }}</button><div class="bld-presets">@foreach(config('building-studio.presets') as $key=>$preset)<button type="button" data-preset="{{ $key }}"><i aria-hidden="true">{{ $preset['icon'] }}</i><strong>{{ __('building.preset_'.$key) }}</strong><span>{{ __('building.preset_'.$key.'_hint') }}</span></button>@endforeach</div>
<div class="identity-card bld-identity-grid">
@include('buildings.identity-fields',['fields'=>['name','display_name','reference','custom_classification']])
<details class="bld-optional"><summary>{{ __('building.optional_details') }}</summary><div class="bld-identity-grid">@include('buildings.identity-fields',['fields'=>['address','country','timezone','construction_year','latitude','longitude']])</div></details>
</div><button class="identity-button bld-next" type="button" data-next="structure">{{ __('building.design_structure') }} →</button>
</section>
<section class="bld-stage" data-panel="structure" hidden>
<div class="bld-section-heading"><span>02 / {{ __('building.structure') }}</span><h2>{{ __('building.assemble') }}</h2><p>{{ __('building.suggestions_hint') }}</p></div>
<details class="bld-generator-panel" data-generator-panel @if(!$draft->building_id) open @endif><summary>{{ __('building.generator_tools') }}</summary><div class="bld-generator identity-card" data-generator>
<div class="identity-field"><label for="bld-level-count">{{ __('building.suggested_levels') }}</label><input id="bld-level-count" data-generate-levels type="number" min="0" max="100" value="1" required></div>
<div class="identity-field"><label for="bld-first-level">{{ __('building.first_level') }}</label><input id="bld-first-level" data-generate-first type="number" min="-20" max="300" value="0" required><small class="field-help">{{ __('building.first_level_hint') }}</small></div>
<div class="identity-field"><label for="bld-space-count">{{ __('building.spaces_per_level') }}</label><input id="bld-space-count" data-generate-spaces type="number" min="0" max="500" value="0" required></div>
@foreach(['area_m2','bed_count','capacity'] as $field)<div class="identity-field"><label for="bld-default-{{ $field }}">{{ __('building.default_'.$field) }}</label><input id="bld-default-{{ $field }}" data-generate-default="{{ $field }}" type="number" min="0" max="{{ $field === 'area_m2' ? 100000000 : 1000 }}" step="{{ $field === 'area_m2' ? '.001' : '1' }}" placeholder="{{ __('building.unknown') }}"></div>@endforeach
<div class="identity-field"><label for="bld-generate-mode">{{ __('building.generate_mode') }}</label><select id="bld-generate-mode" data-generate-mode><option value="append">{{ __('building.append_levels') }}</option><option value="replace">{{ __('building.replace_structure') }}</option></select></div>
<label class="identity-check"><input type="checkbox" data-generate-units><span>{{ __('building.generate_units') }}</span></label><label class="identity-check"><input type="checkbox" data-generate-beds><span>{{ __('building.generate_beds') }}</span></label>
<div class="bld-generator-summary"><p data-generate-summary role="status"></p><small>{{ __('building.room_defaults_hint') }}</small></div>
<button type="button" class="identity-button" data-generate>{{ __('building.generate') }}</button></div></details>
</section>
<div class="bld-workbench" data-workbench hidden>
<aside class="bld-levels identity-card"><div class="bld-mini-heading"><h2>{{ __('building.levels') }}</h2><button type="button" data-add-level class="bld-icon-button" aria-label="{{ __('building.add_level') }}">+</button></div><div data-level-list class="bld-level-list"></div></aside>
<div class="bld-scene-card identity-card">@include('buildings.scene')</div>
<aside class="bld-inspector identity-card"><span class="identity-kicker">{{ __('building.inspector') }}</span><div data-inspector></div></aside>
</div>
<section data-panel="structure" class="bld-stage" hidden>
<div class="bld-bottom-grid"><section class="identity-card"><h2>{{ __('building.accommodation') }}</h2><p>{{ __('building.units_hint') }}</p><div data-unit-list class="bld-unit-list"></div><button class="identity-text-button" type="button" data-add-unit>+ {{ __('building.add_unit') }}</button></section><section class="identity-card"><h2>{{ __('building.transfer') }}</h2><p>{{ __('building.transfer_hint') }}</p><label for="bld-import" class="identity-link">{{ __('building.import') }}</label><label class="identity-check"><input type="checkbox" data-import-template><span>{{ __('building.import_template') }}</span></label><input id="bld-import" type="file" accept=".json,application/json" data-import><button type="button" class="identity-text-button" data-export>{{ __('building.export') }}</button><p class="field-help">{{ __('building.import_hint') }}</p></section></div>
<button class="identity-button bld-next" type="button" data-next="review">{{ __('building.review') }} →</button>
</section>
<section data-panel="review" class="bld-stage" hidden><div class="identity-card bld-review"><span class="identity-kicker">03 / {{ __('building.review') }}</span><h2>{{ __('building.review_title') }}</h2><p>{{ __('building.review_copy') }}</p><div data-review-summary class="bld-review-summary"></div><div class="identity-field"><label for="bld-state">{{ __('building.state') }}</label><select id="bld-state" data-identity="state">@foreach(['draft','active','archived'] as $s)<option value="{{ $s }}">{{ __('building.state_'.$s) }}</option>@endforeach</select></div><p class="field-help">{{ __('building.declared_hint') }}</p><button class="identity-button" type="button" data-complete>{{ __('building.complete') }} ↗</button></div></section>
<footer class="bld-savebar"><div><strong data-save-status role="status">{{ __('building.restored') }}</strong><span data-error role="alert" class="bld-error" tabindex="-1"></span></div><div class="bld-save-actions"><button type="button" class="identity-text-button" data-undo disabled>↶ {{ __('building.undo') }}</button><a class="identity-link" data-fresh hidden href="{{ $draft->building_id ? route('app.declarations.edit',[$organization,$property,\App\Models\Building::findOrFail($draft->building_id)->uuid]) : route('app.declarations.new',[$organization,$property]) }}">{{ __('building.fresh_draft') }}</a><button type="button" class="identity-text-button" data-save>{{ __('building.save_draft') }}</button><button type="button" class="identity-button" data-complete>{{ __('building.save_building') }} ↗</button></div></footer>
</div>
<noscript><div class="identity-notice warning">{{ __('building.js_required') }} <a href="{{ route('app.properties.show',[$organization,$property]) }}">{{ __('building.back_property') }}</a></div></noscript>
<script type="application/json" data-building-data>{!! json_encode(['state'=>$draft->state,'config'=>config('building-studio'),'strings'=>__('building')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!}</script>
</div>
@endsection
