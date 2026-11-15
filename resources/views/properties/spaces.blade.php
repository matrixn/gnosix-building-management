@extends('layouts.identity')
@section('title', __('studio.count_spaces'))
@section('content')
<div class="workspace-heading"><span class="identity-kicker">{{ $organization->name }}</span><h1>{{ __('studio.count_spaces') }}</h1><p>{{ __('studio.spaces_copy') }}</p></div>@include('properties.space-table')
@endsection
