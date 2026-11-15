@extends('identity.auth-frame')
@section('title', __('identity.reset_title'))
@section('auth-content')
<span class="identity-kicker">{{ __('identity.recovery') }}</span><h2 id="auth-title">{{ __('identity.reset_title') }}</h2><p class="identity-lead">{{ __('identity.reset_copy') }}</p>
<form method="POST" action="{{ route('password.update') }}" class="identity-form" data-submit-form>@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-identity.field name="email" :label="__('identity.email')" type="email" :value="$email" required autocomplete="username" maxlength="255"/>
    <x-identity.field name="password" :label="__('identity.new_password')" type="password" required autocomplete="new-password" minlength="12" maxlength="72" :hint="__('identity.password_hint')" autofocus/>
    <x-identity.field name="password_confirmation" :label="__('identity.confirm_password')" type="password" required autocomplete="new-password" minlength="12" maxlength="72"/>
    <x-identity.button data-loading-label="{{ __('identity.saving') }}">{{ __('identity.reset_action') }}</x-identity.button>
</form><a class="identity-link auth-back" href="{{ route('password.request') }}">{{ __('identity.new_reset_link') }}</a>
@endsection
