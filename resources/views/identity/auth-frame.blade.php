@extends('layouts.identity')
@section('content')
<div class="auth-composition">
    <section class="auth-story" aria-label="{{ __('identity.brand_story') }}">
        <span class="identity-kicker">GNOSIX GENESIS / 001</span><h1>{{ __('identity.story_title') }}<br><em>{{ __('identity.story_accent') }}</em></h1><p>{{ __('identity.story_copy') }}</p>
        <div class="architectural-scene" aria-hidden="true"><div class="architectural-grid"></div><div class="architectural-tower"><span></span><span></span><span></span><span></span><span></span><span></span></div><span class="scene-label">BUILDING ID / GNX–001</span></div>
        <span class="auth-story-note"><i></i>{{ __('identity.hospitality_first') }}</span>
    </section>
    <section class="identity-card auth-card" aria-labelledby="auth-title">@yield('auth-content')</section>
</div>
@endsection
