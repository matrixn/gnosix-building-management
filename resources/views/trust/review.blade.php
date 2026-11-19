@extends('layouts.identity')
@section('title', __('trust.portal'))
@section('content')
<div class="trust-shell">
    <a class="identity-link" href="{{ route('handover.portal') }}">← {{ __('trust.portal') }}</a>
    @include('trust.hero')
    <div class="trust-actions">
        <span class="trust-chip">{{ __('trust.state_'.$flow->state) }}</span>
        @include('trust.exports', ['exportRoute' => 'handover.export', 'exportParams' => [$flow->id]])
    </div>
    <p class="field-help">{{ __('trust.frozen') }}</p>
    @include('trust.packet', ['recipientFlow' => $flow->id])
    @if($flow->state === 'under_review' && !$recipient->decision)
        <section class="identity-card trust-panel trust-decisions" aria-label="{{ __('trust.studio') }}">
            <details>
                <summary>{{ __('trust.ask') }}</summary>
                <form method="post" action="{{ route('handover.action', [$flow->id, 'question']) }}">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $flow->revision }}">
                    <label class="identity-field"><span>{{ __('trust.reason') }}</span><textarea name="message" required maxlength="2000"></textarea></label>
                    <button class="identity-button">{{ __('trust.ask') }}</button>
                </form>
            </details>
            <details>
                <summary>{{ __('trust.return') }}</summary>
                <form method="post" action="{{ route('handover.action', [$flow->id, 'return']) }}">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $flow->revision }}">
                    <label class="identity-field"><span>{{ __('trust.reason') }}</span><textarea name="message" required maxlength="2000"></textarea></label>
                    <button class="identity-button">{{ __('trust.return') }}</button>
                </form>
            </details>
            <form method="post" action="{{ route('handover.action', [$flow->id, 'accept']) }}">
                @csrf
                <input type="hidden" name="revision" value="{{ $flow->revision }}">
                <label class="trust-check"><input type="checkbox" name="consent" value="1" required>{{ __('trust.accepted_hint') }}</label>
                <button class="identity-button">{{ __('trust.accept') }}</button>
            </form>
        </section>
    @endif
    @if($questions->isNotEmpty())
        <section class="identity-card trust-panel">
            <h2>{{ __('trust.questions') }}</h2>
            <ul class="trust-list">
                @foreach($questions as $question)
                    <li>
                        <p class="trust-note">{{ $question->message }}</p>
                        @foreach($replies->get($question->id, []) as $reply)<p class="trust-note"><strong>{{ __('trust.state_reply') }}</strong> {{ $reply->message }}</p>@endforeach
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
