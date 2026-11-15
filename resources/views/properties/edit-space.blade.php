@extends('layouts.identity')
@section('title', $space->name)
@section('content')
<div class="workspace-heading"><span class="identity-kicker">{{ $property->name }}</span><h1>{{ $space->name }}</h1></div><section class="identity-card"><form method="POST" action="{{ route('app.spaces.update', [$organization, $property, $space]) }}" class="identity-form" data-space-form>@csrf @method('PATCH')@include('properties.space-fields')<x-identity.button>{{ __('studio.save') }}</x-identity.button></form></section>
@endsection
