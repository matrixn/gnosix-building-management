@extends('layouts.identity')
@section('title', __('workspace.cap_'.str_replace('.', '_', $capability)))
@section('content')
<div class="workspace-heading"><span class="identity-kicker">{{ $organization->name }}</span><h1>{{ __('workspace.cap_'.str_replace('.', '_', $capability)) }}</h1><p>{{ __('workspace.module_preview_hint') }}</p></div><section class="identity-card"><h2>{{ __('workspace.access_ready') }}</h2><p class="identity-lead">{{ __('workspace.module_not_implemented') }}</p><a class="identity-link auth-back" href="{{ route('app.organizations.show', $organization) }}">{{ __('workspace.organization_settings') }} ↗</a></section>
@endsection
