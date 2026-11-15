@php($activeOrganization = app(\App\Support\TenantContext::class)->current())
<section class="identity-card tenant-summary">
    <span class="identity-kicker">{{ __('workspace.active_organization') }}</span>
    <h2>{{ $activeOrganization?->name ?? __('workspace.no_organizations') }}</h2>
    <p>{{ $activeOrganization ? __('workspace.profile_'.$activeOrganization->profile?->profile_key).' · '.__('workspace.kind_'.$activeOrganization->profile?->organization_kind) : __('workspace.create_first_hint') }}</p>
    <a class="identity-link" href="{{ $activeOrganization ? route('app.organizations.show', $activeOrganization) : route('app.organizations.create') }}">{{ $activeOrganization ? __('workspace.organization_settings') : __('workspace.new_organization') }} ↗</a>
</section>
