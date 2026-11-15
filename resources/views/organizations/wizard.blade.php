@php($state = old() ? array_merge($draft->state, old()) : $draft->state)
<section class="organization-wizard" aria-labelledby="profile-heading">
    <span class="identity-kicker">GENESIS / 002</span><h1 id="profile-heading" tabindex="-1">{{ __('workspace.choose_direction') }}</h1><p class="identity-lead">{{ __('workspace.profile_independent') }}</p>
    <form method="POST" action="{{ route('app.organizations.store') }}" data-organization-wizard data-draft-url="{{ route('app.organizations.draft') }}" data-saved="{{ __('workspace.draft_saved') }}" data-saving="{{ __('workspace.saving_draft') }}" data-failed="{{ __('workspace.draft_failed') }}">
        @csrf<input type="hidden" name="version" value="{{ $draft->version }}">
        <fieldset class="industry-choices"><legend>{{ __('workspace.select_profile') }}</legend>
            @foreach($registry->profiles() as $key => $profile)
            <div class="industry-card {{ $profile['enabled'] ? '' : 'is-coming' }}" data-profile-card>
                <label><input type="radio" name="profile_key" value="{{ $key }}" @disabled(!$profile['enabled']) @checked(($state['profile_key'] ?? null) === $key) required><span class="industry-card-content"><span class="industry-state">{{ __($profile['enabled'] ? 'workspace.available' : 'workspace.coming_soon') }}</span><x-identity.profile-illustration :kind="$profile['icon']"/><strong>{{ __('workspace.profile_'.$key) }}</strong><span>{{ __('workspace.description_'.$key) }}</span><small>{{ __('workspace.examples_'.$key) }}</small></span></label>
                <details class="industry-help"><summary>{{ __('workspace.more_context') }}</summary><p>{{ __('workspace.tooltip_'.$key) }}</p><ul>@foreach($profile['capabilities'] as $capability)<li>{{ __('workspace.cap_'.str_replace('.', '_', $capability)) }}</li>@endforeach</ul></details>
            </div>
            @endforeach
        </fieldset>
        <fieldset class="kind-choices" data-kind-choices><legend>{{ __('workspace.organization_shape') }}</legend><p>{{ __('workspace.shape_hint') }}</p>
            @foreach($registry->onboardingKinds() as $kind => $profiles)<label data-kind-profiles="{{ implode(' ', $profiles) }}"><input type="radio" name="organization_kind" value="{{ $kind }}" @checked(($state['organization_kind'] ?? null) === $kind) required><span><strong>{{ __('workspace.kind_'.$kind) }}</strong><small>{{ __('workspace.kind_'.$kind.'_copy') }}</small></span></label>@endforeach
        </fieldset>
        <div class="organization-name identity-card" data-organization-name><span class="identity-kicker">{{ __('workspace.one_detail') }}</span><x-identity.field name="name" :label="__('workspace.organization_name')" :value="$state['name'] ?? ''" maxlength="150" autocomplete="organization" required :hint="__('workspace.name_hint')"/></div>
        <div class="wizard-actions"><button type="submit" class="identity-button secondary" formaction="{{ route('app.organizations.draft') }}" formnovalidate data-save-draft><span>{{ __('workspace.save_draft') }}</span><span aria-hidden="true">↗</span></button><x-identity.button data-complete-organization>{{ __('workspace.create_workspace') }}</x-identity.button></div>
        <noscript><p class="identity-caption">{{ __('workspace.no_js_save') }}</p></noscript>
        <p class="draft-status" data-draft-status role="status">{{ $draft->state ? __('workspace.draft_resumed') : __('workspace.draft_hint') }}</p>
    </form>
</section>
