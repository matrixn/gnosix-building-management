@extends('layouts.identity')
@section('title', 'Workspace Studio')
@section('content')
<div class="studio-heading"><span class="identity-kicker">GNOSIX / WORKSPACE STUDIO</span><h1>{{ __('studio.title') }}</h1><p>{{ __('studio.intro') }}</p><a class="identity-link" href="{{ route('app.properties.index', $organization) }}">{{ __('studio.properties') }} ↗</a> · <a class="identity-link" href="{{ route('app.workspace.settings', $organization) }}">{{ __('studio.organization_settings') }}</a></div>
@if($draft->completed_at)
<section class="identity-card studio-complete"><span class="identity-kicker">{{ __('studio.created') }}</span><h2>{{ __('studio.ready') }}</h2><p>{{ __('studio.edit_later') }}</p><div class="studio-counts">@foreach($draft->result['plan'] as $key => $count)@if($key !== 'nodes')<div><strong>{{ $count }}</strong><span>{{ __('studio.count_'.$key) }}</span></div>@endif
@endforeach</div><a class="identity-button" href="{{ route('app.properties.index', $organization) }}">{{ __('studio.properties') }} ↗</a><form method="POST" action="{{ route('app.studio.new', $organization) }}">@csrf<x-identity.button :secondary="true">{{ __('studio.new_batch') }}</x-identity.button></form></section>
@else
<div class="studio-layout">
<form method="POST" action="{{ route('app.studio.commit', $organization) }}" class="studio-form" data-studio data-save-url="{{ route('app.studio.draft', $organization) }}" data-saved="{{ __('workspace.draft_saved') }}" data-saving="{{ __('workspace.saving_draft') }}" data-failed="{{ __('workspace.draft_failed') }}" data-step="{{ $draft->step }}">@csrf
<input type="hidden" name="version" value="{{ $draft->version }}"><input type="hidden" name="step" value="{{ $draft->step }}"><input type="hidden" name="guided" value="0">
<label class="identity-check"><input type="checkbox" name="guided" value="1" @checked($draft->guided) data-guided><span>{{ __('studio.guided') }}</span></label>
<nav class="studio-steps" aria-label="{{ __('studio.steps') }}">@foreach($schema['steps'] as $step)<button type="button" data-studio-step="{{ $step }}">{{ __('studio.step_'.$step) }}</button>@endforeach</nav>
@foreach(['vision', 'structure'] as $step)
<section class="studio-step identity-card" data-step-panel="{{ $step }}">
<h2>{{ __('studio.step_'.$step) }}</h2>
@if($step === 'vision')
<fieldset class="studio-scenarios"><legend>{{ __('studio.scenario') }}</legend>@foreach($schema['scenarios'] as $key => $definition)<label><input type="radio" name="answers[scenario]" value="{{ $key }}" @checked($resolved['scenario'] === $key)><span><i aria-hidden="true">{{ $definition['icon'] }}</i><strong>{{ __('studio.scenario_'.$key) }}</strong><small>{{ __('studio.scenario_'.$key.'_copy') }}</small></span></label>@endforeach</fieldset>
@endif
<div class="studio-fields">
@foreach($schema['fields'] as $key => $field)
@if($field['step'] === $step)
@php($value = $state[$key] ?? $resolved[$key])
@php($edited = array_key_exists($key, $state) && $value != ($schema['scenarios'][$resolved['scenario']]['defaults'][$key] ?? $field['default']))
<div class="studio-question identity-field {{ $field['type'] === 'checks' ? 'studio-wide' : '' }}" data-question="{{ $key }}" @if(!$questions->visible($field, $resolved)) hidden @endif>
@if($field['type'] === 'checks')
<fieldset><legend>{{ __('studio.field_'.$key) }}</legend><input type="hidden" name="answers[{{ $key }}]" value=""><div class="studio-check-options">@foreach($schema[$field['options']] as $option)<label><input type="checkbox" name="answers[{{ $key }}][]" value="{{ $option }}" @checked(in_array($option, is_array($value) ? $value : [], true))> {{ __('studio.option_'.$option) }}</label>@endforeach</div></fieldset>
@else
<label for="studio-{{ $key }}">{{ __('studio.field_'.$key) }}</label>
@if($field['type'] === 'select')<select id="studio-{{ $key }}" name="answers[{{ $key }}]" data-answer="{{ $key }}" data-edited="{{ $edited ? '1' : '0' }}">@foreach($schema[$field['options']] as $option)<option value="{{ $option }}" @selected($value === $option)>{{ __('studio.type_'.$option) }}</option>@endforeach</select>
@else<input id="studio-{{ $key }}" name="answers[{{ $key }}]" type="{{ $field['type'] }}" value="{{ $value }}" data-answer="{{ $key }}" data-edited="{{ $edited ? '1' : '0' }}" @if(isset($field['min'])) min="{{ $field['min'] }}" max="{{ $field['max'] }}" @else maxlength="{{ $field['max'] }}" @endif>
@endif
@endif
<details class="studio-help"><summary>{{ __('studio.why') }}</summary><p>{{ __('studio.help_'.$key) }}</p></details>
</div>
@endif
@endforeach
</div></section>
@endforeach
<section class="studio-step identity-card" data-step-panel="review"><h2>{{ __('studio.step_review') }}</h2><p>{{ __('studio.review_copy') }}</p><p class="identity-caption">{{ __('studio.memory_copy') }}</p><p class="identity-notice warning" data-studio-limit hidden role="status">{{ __('studio.batch_limit', ['limit' => $schema['batch_node_limit']]) }}</p><x-identity.button data-studio-commit>{{ __('studio.create') }}</x-identity.button></section>
<div class="studio-navigation"><button type="button" class="identity-text-button" data-studio-back hidden>← {{ __('studio.back') }}</button><button type="submit" class="identity-text-button" formaction="{{ route('app.studio.draft', $organization) }}" formnovalidate data-studio-save>{{ __('workspace.save_draft') }}</button><button type="button" class="identity-button" data-studio-next hidden>{{ __('studio.next') }} →</button></div>
<p class="draft-status" role="status" data-studio-status>{{ $draft->state ? __('workspace.draft_resumed') : __('studio.start_hint') }}</p>
<noscript><p class="identity-caption">{{ __('studio.no_js') }}</p></noscript>
</form>
<aside class="studio-preview identity-card" aria-label="{{ __('studio.live_preview') }}"><span class="identity-kicker">{{ __('studio.live_preview') }}</span><div class="studio-blueprint" aria-hidden="true"><span></span><span></span><span></span></div><h2 data-preview-name>{{ $resolved['property_name'] ?: $organization->name }}</h2><div class="studio-counts" aria-live="polite">@foreach($questions->plan($resolved) as $key => $count)@if($key !== 'nodes')<div><strong data-preview-count="{{ $key }}">{{ $count }}</strong><span>{{ __('studio.count_'.$key) }}</span></div>@endif
@endforeach</div><p class="identity-caption">{{ __('studio.preview_hint') }}</p><div class="studio-progress" aria-hidden="true"><span data-studio-progress></span></div></aside>
</div>
<script id="studio-schema" type="application/json">{!! json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endsection
