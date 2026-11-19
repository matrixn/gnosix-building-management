@extends('layouts.identity')
@section('title', __('trust.invitation'))
@section('content')
<section class="identity-card trust-panel trust-entry">
    <h1>{{ __('trust.invitation') }}</h1>
    <p>{{ __('trust.invitation_private') }}</p>
    @auth('web')
        @if(auth('web')->user()->hasVerifiedEmail())
            <form method="post" action="{{ route('handover.redeem') }}" data-trust-invitation>
                @csrf
                <label class="identity-field"><span>{{ __('trust.token') }}</span>
                    <input type="password" name="invitation_token" required minlength="64" maxlength="64" autocomplete="off" spellcheck="false">
                </label>
                <button class="identity-button">{{ __('trust.redeem') }}</button>
            </form>
        @else
            <a class="identity-button" href="{{ route('verification.notice') }}">{{ __('identity.verify_action') }}</a>
        @endif
    @else
        <div class="trust-actions">
            <a class="identity-button" href="{{ route('login') }}">{{ __('identity.sign_in') }}</a>
            <a class="identity-link" href="{{ route('register') }}">{{ __('identity.create_account') }}</a>
        </div>
    @endauth
</section>
@endsection
