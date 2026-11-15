@extends('layouts.identity')
@section('title', __('identity.verify_title'))
@section('content')
<section class="identity-card verification-card"><span class="identity-orbit" aria-hidden="true">↗</span><span class="identity-kicker">{{ __('identity.one_more_step') }}</span><h1>{{ __('identity.verify_title') }}</h1><p class="identity-lead">{{ __('identity.verify_copy') }}</p><strong class="verification-address">{{ auth('web')->user()->email }}</strong><p class="identity-caption">{{ __('identity.verify_instructions') }}</p><form method="POST" action="{{ route('verification.send') }}" data-submit-form>@csrf<x-identity.button data-loading-label="{{ __('identity.sending') }}">{{ __('identity.resend_verification') }}</x-identity.button></form><p class="identity-caption">{{ __('identity.verification_limit') }}</p></section>
@endsection
