@extends('layouts.identity')
@section('title', __('identity.welcome_title'))
@section('content')
<div class="onboarding" data-onboarding @if($errors->any()) data-onboarding-errors @endif>
    <ol class="onboarding-steps" aria-label="{{ __('identity.onboarding_steps') }}"><li data-welcome-step aria-current="step">01 / {{ __('identity.welcome_step') }}</li><li data-profile-step>02 / {{ __('identity.profile_step') }}</li></ol>
    <section class="onboarding-intro" data-onboarding-intro><div class="intro-grid" aria-hidden="true"></div><div class="intro-monogram" aria-hidden="true">G<span>×</span></div><span class="identity-kicker">GENESIS / HOSPITALITY</span><h1>{{ __('identity.welcome_title') }}</h1><p>{{ __('identity.welcome_copy') }}</p><div class="intro-progress" aria-hidden="true"><span></span></div><button type="button" class="identity-button" data-intro-continue hidden><span>{{ __('identity.continue') }}</span><span aria-hidden="true">→</span></button><span class="identity-caption">{{ __('identity.intro_hint') }}</span></section>
    <section class="profile-selection" data-profile-selection aria-labelledby="profile-heading"><span class="identity-kicker">{{ __('identity.your_perspective') }}</span><h1 id="profile-heading" tabindex="-1">{{ __('identity.profile_title') }}</h1><p class="identity-lead">{{ __('identity.profile_copy') }}</p>
        <form method="POST" action="{{ route('app.onboarding.complete') }}" data-submit-form>@csrf
            <fieldset class="profile-choices"><legend>{{ __('identity.choose_profile') }}</legend>
                @foreach(['owner' => '◇', 'manager' => '◎', 'technical' => '⌘'] as $profile => $icon)<label class="profile-choice"><input type="radio" name="profile_type" value="{{ $profile }}" required @checked(old('profile_type') === $profile)><span class="profile-choice-content"><span class="profile-icon" aria-hidden="true">{{ $icon }}</span><strong>{{ __('identity.profile_'.$profile) }}</strong><small>{{ __('identity.profile_'.$profile.'_copy') }}</small></span></label>@endforeach
            </fieldset>
            <details class="context-tip"><summary>{{ __('identity.why_profile') }}</summary><p>{{ __('identity.profile_explanation') }}</p></details>
            <x-identity.button data-loading-label="{{ __('identity.saving') }}">{{ __('identity.enter_workspace') }}</x-identity.button>
        </form>
    </section>
</div>
@endsection
