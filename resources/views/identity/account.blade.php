@extends('layouts.identity')
@section('title', __('identity.account'))
@section('content')
@php($user = auth('web')->user())
<div class="workspace-heading"><span class="identity-kicker">{{ __('identity.your_identity') }}</span><h1>{{ __('identity.account') }}</h1><p>{{ __('identity.account_copy') }}</p></div>
<section class="identity-card account-section" aria-labelledby="profile-title"><div><span class="identity-kicker">01 / {{ __('identity.profile_step') }}</span><h2 id="profile-title">{{ __('identity.personal_details') }}</h2><p>{{ __('identity.email_change_hint') }}</p></div><form method="POST" action="{{ route('app.account.update') }}" class="identity-form" data-submit-form>@csrf @method('PATCH')
    <x-identity.field name="name" :label="__('identity.full_name')" :value="$user->name" required autocomplete="name" maxlength="120"/>
    <x-identity.field name="email" :label="__('identity.email')" type="email" :value="$user->email" required autocomplete="username" maxlength="255"/>
    <div class="identity-field"><label for="account-locale">{{ __('identity.language') }}</label><select name="locale" id="account-locale" required><option value="ro" @selected(old('locale', $user->locale) === 'ro')>Română</option><option value="en" @selected(old('locale', $user->locale) === 'en')>English</option></select></div>
    <x-identity.field name="current_password" id="profile-current-password" :label="__('identity.current_password')" type="password" autocomplete="current-password" :hint="__('identity.email_password_hint')"/>
    <x-identity.button data-loading-label="{{ __('identity.saving') }}">{{ __('identity.save_profile') }}</x-identity.button>
</form></section>
<section class="identity-card account-section" id="security" aria-labelledby="password-title"><div><span class="identity-kicker">02 / {{ __('identity.security') }}</span><h2 id="password-title">{{ __('identity.change_password') }}</h2><p>{{ __('identity.password_change_hint') }}</p></div><form method="POST" action="{{ route('app.password.update') }}" class="identity-form" data-submit-form>@csrf @method('PUT')
    <x-identity.field name="current_password" id="security-current-password" :label="__('identity.current_password')" type="password" required autocomplete="current-password"/>
    <x-identity.field name="password" :label="__('identity.new_password')" type="password" required autocomplete="new-password" minlength="12" maxlength="72" :hint="__('identity.password_hint')"/>
    <x-identity.field name="password_confirmation" :label="__('identity.confirm_password')" type="password" required autocomplete="new-password" minlength="12" maxlength="72"/>
    <x-identity.button data-loading-label="{{ __('identity.saving') }}">{{ __('identity.update_password') }}</x-identity.button>
</form></section>
<section class="identity-card account-section" aria-labelledby="sessions-title"><div><span class="identity-kicker">03 / {{ __('identity.sessions') }}</span><h2 id="sessions-title">{{ __('identity.control_access') }}</h2><p>{{ __('identity.sessions_copy') }}</p><p class="identity-caption">{{ __('identity.last_sign_in') }}: {{ $user->last_signed_in_at?->format('d.m.Y H:i') ?? '—' }} UTC</p></div><form method="POST" action="{{ route('app.sessions.destroy') }}" class="identity-form" data-submit-form>@csrf @method('DELETE')
    <x-identity.field name="current_password" id="sessions-current-password" :label="__('identity.current_password')" type="password" required autocomplete="current-password"/>
    <x-identity.button :secondary="true" data-loading-label="{{ __('identity.saving') }}">{{ __('identity.revoke_sessions') }}</x-identity.button>
</form></section>
@endsection
