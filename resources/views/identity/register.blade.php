@extends('identity.auth-frame')
@section('title', __('identity.create_account'))
@section('auth-content')
<span class="identity-kicker">{{ __('identity.start_story') }}</span><h2 id="auth-title">{{ __('identity.register_title') }}</h2><p class="identity-lead">{{ __('identity.register_copy') }}</p>
<form method="POST" action="{{ route('register.store') }}" class="identity-form" data-submit-form>@csrf
    <x-identity.field name="name" :label="__('identity.full_name')" required autocomplete="name" minlength="2" maxlength="120" autofocus/>
    <x-identity.field name="email" :label="__('identity.email')" type="email" required autocomplete="username" maxlength="255"/>
    <x-identity.field name="password" :label="__('identity.password')" type="password" required autocomplete="new-password" minlength="12" maxlength="72" :hint="__('identity.password_hint')"/>
    <x-identity.field name="password_confirmation" :label="__('identity.confirm_password')" type="password" required autocomplete="new-password" minlength="12" maxlength="72"/>
    <label class="identity-check"><input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))><span>{{ __('identity.privacy_acknowledge') }} <a href="{{ \App\Support\PublicSite::privacy(app()->getLocale()) }}" class="identity-link">{{ __('identity.privacy') }}</a>.</span></label>
    <x-identity.button data-loading-label="{{ __('identity.creating_account') }}">{{ __('identity.create_account') }}</x-identity.button>
</form><p class="auth-switch">{{ __('identity.have_account') }} <a href="{{ route('login') }}">{{ __('identity.sign_in') }}</a></p>
@endsection
