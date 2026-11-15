@extends('layouts.identity')
@section('title', __('identity.welcome_title'))
@section('content')
<div class="onboarding" data-onboarding @if($errors->any() || $draft->version > 1) data-onboarding-errors @endif>
    <ol class="onboarding-steps" aria-label="{{ __('identity.onboarding_steps') }}"><li data-welcome-step aria-current="step">01 / {{ __('identity.welcome_step') }}</li><li data-profile-step>02 / {{ __('identity.profile_step') }}</li></ol>
    <section class="onboarding-intro" data-onboarding-intro><div class="intro-grid" aria-hidden="true"></div><div class="intro-monogram" aria-hidden="true">G<span>×</span></div><span class="identity-kicker">GENESIS / HOSPITALITY</span><h1>{{ __('identity.welcome_title') }}</h1><p>{{ __('identity.welcome_copy') }}</p><div class="intro-progress" aria-hidden="true"><span></span></div><button type="button" class="identity-button" data-intro-continue hidden><span>{{ __('identity.continue') }}</span><span aria-hidden="true">→</span></button><span class="identity-caption">{{ __('identity.intro_hint') }}</span></section>
    <section class="profile-selection" data-profile-selection>
        @include('organizations.wizard')
    </section>
</div>
@endsection
