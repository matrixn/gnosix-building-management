@extends('layouts.identity')
@section('title', __('identity.error_title'))
@section('content')
<section class="identity-card verification-card"><span class="identity-kicker">GNOSIX / {{ $errorCode }}</span><h1>{{ __('identity.error_title') }}</h1><p class="identity-lead">{{ __('identity.error_'.$errorCode) }}</p><a class="identity-button auth-back" href="{{ route('login', ['lang' => app()->getLocale()]) }}"><span>{{ __('identity.back_login') }}</span><span aria-hidden="true">↗</span></a>@if($errorCode === 403)<p class="identity-caption"><a class="identity-link" href="{{ auth('web')->check() ? route('verification.notice') : route('password.request') }}">{{ __('identity.request_link') }}</a></p>@endif</section>
@endsection
