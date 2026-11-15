@extends('identity.auth-frame')
@section('title', __('identity.sign_in'))
@section('auth-content')
<span class="identity-kicker">{{ __('identity.welcome_back') }}</span><h2 id="auth-title">{{ __('identity.sign_in_title') }}</h2><p class="identity-lead">{{ __('identity.sign_in_copy') }}</p>
<form method="POST" action="{{ route('login.store') }}" class="identity-form" data-submit-form>@csrf
    <x-identity.field name="email" :label="__('identity.email')" type="email" required autocomplete="username" maxlength="255" autofocus/>
    <x-identity.field name="password" :label="__('identity.password')" type="password" required autocomplete="current-password" maxlength="255"/>
    <a class="identity-link form-link" href="{{ route('password.request') }}">{{ __('identity.forgot_password') }}</a>
    <x-identity.button data-loading-label="{{ __('identity.signing_in') }}">{{ __('identity.sign_in') }}</x-identity.button>
</form>
<p class="auth-switch">{{ __('identity.no_account') }} <a href="{{ route('register') }}">{{ __('identity.create_account') }}</a></p><p class="identity-caption">{{ __('identity.customer_access') }}</p>
@endsection
