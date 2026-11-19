@extends('layouts.identity')
@section('title', __('trust.issued'))
@section('content')
<section class="identity-card trust-panel trust-entry">
    <h1>{{ __('trust.issued') }}</h1>
    <p>{{ __('trust.issued_hint') }}</p>
    <label class="identity-field">
        <span>{{ __('trust.invitation') }}</span>
        <input readonly type="text" value="{{ $link }}" data-trust-link autocomplete="off" spellcheck="false">
    </label>
    <div class="trust-actions">
        <button type="button" hidden class="identity-button" data-trust-copy data-copied="{{ __('trust.copied') }}">{{ __('trust.copy') }}</button>
        <a class="identity-link" href="{{ $back }}">{{ __('trust.back_studio') }} ↗</a>
    </div>
    <p class="field-help" data-trust-copy-status role="status"></p>
</section>
@endsection
