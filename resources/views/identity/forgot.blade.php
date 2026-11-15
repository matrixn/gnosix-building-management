@extends('identity.auth-frame')
@section('title', __('identity.forgot_password'))
@section('auth-content')
<span class="identity-kicker">{{ __('identity.recovery') }}</span><h2 id="auth-title">{{ __('identity.forgot_title') }}</h2><p class="identity-lead">{{ __('identity.forgot_copy') }}</p>
<form method="POST" action="{{ route('password.email') }}" class="identity-form" data-submit-form>@csrf
    <x-identity.field name="email" :label="__('identity.email')" type="email" required autocomplete="email" maxlength="255" autofocus/>
    <x-identity.button data-loading-label="{{ __('identity.sending') }}">{{ __('identity.send_reset') }}</x-identity.button>
</form><a class="identity-link auth-back" href="{{ route('login') }}">← {{ __('identity.back_login') }}</a>
@endsection
