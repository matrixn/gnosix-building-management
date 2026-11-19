@extends('layouts.identity')
@section('title', __('trust.studio'))
@section('content')
@php
    $oldText = fn ($name, $default = '') => is_scalar($value = old($name, $default)) ? (string) $value : '';
    $parameters = [$organization, $property, $building->uuid, $flow->id];
    $selectedVersions = old('versions', $selection['versions'] ?? []);
    $selectedSections = old('sections', $selection['sections'] ?? []);
@endphp
<div class="trust-shell">
    <a class="identity-link" href="{{ route('app.trust.show', [$organization, $property, $building->uuid]) }}">← {{ __('trust.title') }}</a>
    <header class="trust-hero">
        <div><span class="identity-kicker">{{ $building->reference }}</span>
            <h1>{{ __('trust.studio') }}</h1>
            <p>{{ $building->name }}</p>
            <span class="trust-chip">{{ __('trust.state_'.$flow->state) }}</span>
        </div>
        <div class="trust-motif" aria-hidden="true"><i></i><i></i><i></i></div>
    </header>
    @if($editable)
        <form class="identity-card trust-panel trust-form" method="post" action="{{ route('app.trust.action', [...$parameters, 'prepare']) }}">
            @csrf
            <input type="hidden" name="revision" value="{{ $flow->revision }}">
            <fieldset>
                <legend>{{ __('trust.disclose') }}</legend>
                <p class="field-help">{{ __('trust.disclosure_hint') }}</p>
                <div class="trust-options">
                    @foreach(\App\Trust\Passport::SECTIONS as $section)
                        @if($allowed[$section])
                            <label class="trust-check"><input type="checkbox" name="sections[]" value="{{ $section }}" @checked(in_array($section, (array) $selectedSections, true))>{{ __('trust.'.$section) }}</label>
                        @endif
                    @endforeach
                </div>
            </fieldset>
            @if($docs)
                <details @if($selectedVersions || request()->query('documents')) open @endif>
                    <summary>{{ __('trust.documents') }}</summary>
                    <p class="field-help">{{ __('trust.document_hint') }}</p>
                    <div class="trust-doc-options">
                        @foreach($docs as $document)
                            <label class="trust-check">
                                <input type="checkbox" name="versions[]" value="{{ $document->id }}" @checked(in_array($document->id, (array) $selectedVersions, true))>
                                <span>{{ $document->original_name }} · {{ __('trust.version', ['number' => $document->number]) }}<small>{{ $document->created_at->toDateString() }}</small></span>
                            </label>
                        @endforeach
                        @foreach($otherDocs as $document)
                            <label class="trust-check">
                                <input type="checkbox" name="versions[]" value="{{ $document->id }}" checked>
                                <span>{{ $document->original_name }} · {{ __('trust.version', ['number' => $document->number]) }}<small>{{ $document->created_at->toDateString() }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    {{ $docs->appends(request()->only('events'))->links() }}
                </details>
            @endif
            <fieldset>
                <legend>{{ __('trust.checklist') }}</legend>
                @foreach(\App\Trust\Passport::CHECKS as $check)
                    <label class="trust-check"><input type="checkbox" name="checks[]" value="{{ $check }}" @checked(in_array($check, (array) old('checks', $selection['checks'] ?? []), true)) required>{{ __('trust.check_'.$check) }}</label>
                @endforeach
            </fieldset>
            <fieldset>
                <legend>{{ __('trust.authority') }}</legend>
                <p class="field-help">{{ __('trust.authority_hint') }}</p>
                <label class="identity-field"><span>{{ __('trust.authority_basis') }}</span>
                    <input name="authority_basis" required minlength="8" maxlength="1500" value="{{ $oldText('authority_basis', $flow->authority_basis) }}">
                </label>
                <label class="trust-check"><input type="checkbox" name="authority" value="1" required>{{ __('trust.authority_check') }}</label>
            </fieldset>
            <fieldset>
                <legend>{{ __('trust.after') }}</legend>
                <label class="identity-field"><span>{{ __('trust.responsibilities') }}</span>
                    <textarea name="responsibilities" maxlength="2000">{{ $oldText('responsibilities', $selection['responsibilities'] ?? '') }}</textarea>
                </label>
                <label class="identity-field"><span>{{ __('trust.recipient_org') }}</span>
                    <input type="number" name="recipient_org" min="1" value="{{ $oldText('recipient_org', $flow->recipient_organization_id) }}" aria-describedby="trust-recipient-hint">
                    <small class="field-help" id="trust-recipient-hint">{{ __('trust.recipient_org_hint') }}</small>
                </label>
                <label class="identity-field"><span>{{ __('trust.access_days') }}</span>
                    <input type="number" name="access_days" min="0" max="365" required value="{{ $oldText('access_days', $flow->access_days) }}" aria-describedby="trust-access-hint">
                    <small class="field-help" id="trust-access-hint">{{ __('trust.access_hint') }}</small>
                </label>
            </fieldset>
            <button class="identity-button">{{ __('trust.prepare') }}</button>
        </form>
    @endif
    @if($flow->snapshot)
        <section class="identity-card trust-panel" aria-label="{{ __('trust.authority') }}">
            <dl class="trust-values">
                <div><dt>{{ __('trust.before') }}</dt><dd>{{ __('trust.before_hint') }}</dd></div>
                <div><dt>{{ __('trust.after') }}</dt><dd>{{ __('trust.after_hint', ['days' => $flow->access_days]) }}</dd></div>
                <div><dt>{{ __('trust.authority_basis') }}</dt><dd>{{ $flow->authority_basis }}</dd></div>
                @if($flow->recipient_organization_id)<div><dt>{{ __('trust.recipient_org') }}</dt><dd>{{ $flow->recipient_organization_id }}</dd></div>@endif
            </dl>
        </section>
    @endif
    <section class="identity-card trust-panel">
        <h2>{{ __('trust.recipients') }}</h2>
        @if($packet && in_array($flow->state, ['prepared', 'invited'], true))
            <form method="post" action="{{ route('app.trust.action', [...$parameters, 'invite']) }}" class="trust-form">
                @csrf
                <input type="hidden" name="revision" value="{{ $flow->revision }}">
                <p class="field-help">{{ __('trust.invite_hint') }}</p>
                <label class="identity-field"><span>{{ __('trust.invite_email') }}</span><input type="email" name="email" maxlength="254" required autocomplete="off" value="{{ $oldText('email') }}"></label>
                <label class="identity-field"><span>{{ __('trust.invite_days') }}</span><input type="number" name="days" min="1" max="14" required value="{{ $oldText('days', 7) }}"></label>
                <button class="identity-button">{{ __('trust.invite') }}</button>
            </form>
        @endif
        <ul class="trust-list">
            @forelse($recipients as $recipient)
                <li>
                    <strong>{{ $recipient->email }}</strong>
                    <span>{{ __('trust.state_'.($recipient->revoked_at ? 'revoked' : ($recipient->decision ?? ($recipient->redeemed_at ? 'under_review' : 'invited')))) }}</span>
                    <span>{{ __('trust.expires', ['date' => $recipient->expires_at]) }}</span>
                    @if($recipient->ends_at)<span>{{ __('trust.grant_dates', ['start' => $recipient->starts_at, 'end' => $recipient->ends_at]) }}</span>@endif
                    @if(!$recipient->revoked_at && (in_array($flow->state, ['invited', 'under_review', 'accepted'], true) || $recipient->ends_at && $flow->state === 'completed'))
                        <details>
                            <summary>{{ __('trust.revoke') }}</summary>
                            <form method="post" action="{{ route('app.trust.action', [...$parameters, 'revoke']) }}">
                                @csrf
                                <input type="hidden" name="revision" value="{{ $flow->revision }}">
                                <input type="hidden" name="recipient" value="{{ $recipient->id }}">
                                <label class="identity-field"><span>{{ __('trust.reason') }}</span><textarea name="message" maxlength="2000" required></textarea></label>
                                <button class="identity-button">{{ __('trust.revoke') }}</button>
                            </form>
                        </details>
                    @endif
                </li>
            @empty
                <li>{{ __('trust.empty') }}</li>
            @endforelse
        </ul>
    </section>
    @if(($flow->state === 'accepted' && $packet) || !in_array($flow->state, ['completed', 'cancelled', 'returned'], true))
        <section class="identity-card trust-panel trust-decisions" aria-label="{{ __('trust.studio') }}">
            @if($flow->state === 'accepted' && $packet)
                <form method="post" action="{{ route('app.trust.action', [...$parameters, 'complete']) }}">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $flow->revision }}">
                    <label class="trust-check"><input type="checkbox" name="confirm" value="1" required>{{ __('trust.complete_check') }}</label>
                    <button class="identity-button">{{ __('trust.complete') }}</button>
                </form>
            @endif
            @if(!in_array($flow->state, ['completed', 'cancelled', 'returned'], true))
                <details>
                    <summary>{{ __('trust.cancel') }}</summary>
                    <form method="post" action="{{ route('app.trust.action', [...$parameters, 'cancel']) }}">
                        @csrf
                        <input type="hidden" name="revision" value="{{ $flow->revision }}">
                        <label class="identity-field"><span>{{ __('trust.reason') }}</span><textarea name="message" maxlength="2000" required></textarea></label>
                        <button class="identity-button">{{ __('trust.cancel') }}</button>
                    </form>
                </details>
            @endif
        </section>
    @endif
    @if($packet)
        <div>
            <h2>{{ __('trust.flow_preview') }}</h2>
            <p class="field-help">{{ __('trust.captured', ['time' => $packet['captured_at']]) }} · {{ __('trust.frozen') }}</p>
            @include('trust.packet', ['passport' => $packet, 'internal' => false])
        </div>
    @endif
    <section class="identity-card trust-panel">
        <h2>{{ __('trust.timeline') }}</h2>
        <ol class="trust-timeline">
            @foreach($events as $event)
                <li>
                    <strong>{{ __('trust.state_'.$event->action) }}</strong>
                    <time>{{ $event->created_at }}</time>
                    @if($event->actor_id)<span>#{{ $event->actor_id }}</span>@endif
                    @if($target = $recipients->firstWhere('id', $event->recipient_reference))<span>{{ $target->email }}</span>@endif
                    @if($event->message)<p class="trust-note">{{ $event->message }}</p>@endif
                    @if($event->action === 'question' && in_array($flow->state, ['under_review', 'accepted'], true))
                        <details>
                            <summary>{{ __('trust.reply') }}</summary>
                            <form method="post" action="{{ route('app.trust.action', [...$parameters, 'reply']) }}">
                                @csrf
                                <input type="hidden" name="revision" value="{{ $flow->revision }}">
                                <input type="hidden" name="question" value="{{ $event->id }}">
                                <label class="identity-field"><span>{{ __('trust.reply') }}</span><textarea name="message" required maxlength="2000" aria-describedby="trust-reply-{{ $event->id }}"></textarea></label>
                                <p class="field-help" id="trust-reply-{{ $event->id }}">{{ __('trust.reply_hint') }}</p>
                                <button class="identity-button">{{ __('trust.reply') }}</button>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ol>
        {{ $events->appends(request()->only('documents'))->links() }}
    </section>
</div>
@endsection
