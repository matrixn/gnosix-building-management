@extends('layouts.identity')
@section('title', __('building.delete_building'))
@section('content')
<section class="identity-card bld-delete-confirm" aria-labelledby="delete-title">
<span class="identity-kicker">{{ $organization->name }} / {{ $property->name }}</span>
<h1 id="delete-title">{{ __('building.delete_question') }}</h1>
<h2>{{ $building->display_name ?: $building->name }}</h2><p class="identity-caption">{{ $building->reference }}</p>
<p>{{ __('building.delete_explanation') }}</p>
<p>{{ $building->floors_count }} {{ __('building.levels') }} · {{ $building->spaces_count }} {{ __('building.spaces') }}</p>
<form method="POST" action="{{ route('app.declarations.destroy', [$organization,$property,$building->uuid]) }}" data-submit-form>
@csrf @method('DELETE')<input type="hidden" name="confirmed" value="1"><input type="hidden" name="version" value="{{ $building->declaration_version }}">
<div class="bld-delete-actions"><a class="identity-link" href="{{ route('app.spaces.index', $organization) }}">{{ __('building.cancel_delete') }}</a><button class="bld-danger" type="submit" data-loading-label="{{ __('building.deleting') }}"><span>{{ __('building.confirm_delete') }}</span></button></div>
</form>
</section>
@endsection
