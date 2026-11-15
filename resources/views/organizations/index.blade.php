@extends('layouts.identity')
@section('title', __('workspace.organizations'))
@section('content')
<div class="workspace-heading"><span class="identity-kicker">GNOSIX / ORGANIZATIONS</span><h1>{{ __('workspace.organizations') }}</h1><p>{{ __('workspace.organizations_copy') }}</p></div>
<a href="{{ route('app.organizations.create') }}" class="identity-button"><span>{{ __('workspace.new_organization') }}</span><span aria-hidden="true">+</span></a>
<div class="organization-list">
    @forelse($organizations as $organization)
    <article class="identity-card"><span class="identity-kicker">{{ __('workspace.role_'.$organization->pivot->role) }}</span><h2>{{ $organization->name }}</h2><p>{{ __('workspace.profile_'.$organization->profile?->profile_key) }} · {{ __('workspace.kind_'.$organization->profile?->organization_kind) }}</p><div class="organization-actions"><a class="identity-link" href="{{ route('app.organizations.show', $organization) }}">{{ __('workspace.organization_settings') }} ↗</a><form method="POST" action="{{ route('app.organizations.switch', $organization) }}">@csrf<x-identity.button :secondary="true">{{ app(\App\Support\TenantContext::class)->current()?->id === $organization->id ? __('workspace.active_organization') : __('workspace.switch') }}</x-identity.button></form></div></article>
    @empty<p class="identity-caption">{{ __('workspace.no_organizations') }}</p>@endforelse
</div>
@endsection
