@extends('layouts.identity')
@section('title', __('time.title').' · '.$building->name)
@section('content')
@php
    $namesBefore = array_column($state['rows'], 'label', 'key');
    $namesAfter = $other ? array_column($other['rows'], 'label', 'key') : [];
    $namesBefore += collect($state['rows'])->whereIn('kind', ['floor','space'])->mapWithKeys(fn ($row) => [str($row['key'])->after(':')->value() => $row['label']])->all();
    $namesAfter += $other ? collect($other['rows'])->whereIn('kind', ['floor','space'])->mapWithKeys(fn ($row) => [str($row['key'])->after(':')->value() => $row['label']])->all() : [];
    $show = route('app.time-machine.show', [$organization, $property, $building->uuid]);
    $partial = $state['partial'] || ($other['partial'] ?? false);
@endphp
<main class="tm-shell">
    <header class="tm-header">
        <a class="identity-link" href="{{ route('app.time-machine.index', $organization) }}">{{ __('time.title') }} / {{ $property->name }}</a>
        <h1>{{ $building->display_name ?: $building->name }}</h1>
        <p>{{ __('time.subtitle') }}</p>
    </header>
    <form class="tm-controls" action="{{ $show }}" method="get">
        <label>{{ __('time.valid') }} <input required type="date" name="at" value="{{ $query['at'] }}" min="1000-01-01" max="{{ $today }}"></label>
        <label>{{ __('time.compare') }} <input type="date" name="compare" value="{{ $query['compare'] }}" min="1000-01-01" max="{{ $today }}"></label>
        <label>{{ __('time.known') }} <input type="datetime-local" step="1" name="known" value="{{ $query['known'] }}"></label>
        <label>{{ __('time.mode') }} <select name="mode">
            @foreach(['documented','then'] as $mode)<option value="{{ $mode }}" @selected($query['mode'] === $mode)>{{ __('time.'.$mode) }}</option>@endforeach
        </select></label>
        <label>{{ __('time.scope') }} <select name="section">
            @foreach(['all','structure','equipment','relations','maintenance','evidence'] as $section)<option value="{{ $section }}" @selected($query['section'] === $section)>{{ __('time.'.$section) }}</option>@endforeach
        </select></label>
        <button class="identity-button identity-button--primary" type="submit">{{ __('time.apply') }}</button>
    </form>
    @if($errors->any())
        <ul class="tm-warning" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif

    <div class="tm-context">
        <span>{{ $state['valid'] }} · {{ __('time.known') }} {{ $state['knowledge'] }}</span>
        @if($other)<span>{{ $other['valid'] }} · {{ __('time.known') }} {{ $other['knowledge'] }}</span>@endif
    </div>
    <p class="tm-warning">{{ __('time.missing') }} @if($partial) {{ __('time.limit') }} @endif</p>
    <div class="tm-atlas">
        <section class="tm-panel">
            <h2>{{ __('time.schematic') }}</h2>
            <p>{{ __('time.position_hint') }}</p>
            @if($other)
                <div class="tm-sides">
                    @include('time-machine.schematic', ['side' => $state])
                    @include('time-machine.schematic', ['side' => $other])
                </div>
            @else
                @include('time-machine.schematic', ['side' => $state])
            @endif
        </section>
        <section class="tm-panel">
            <h2>{{ __('time.timeline') }}</h2>
            <ol class="tm-track">
                @forelse($markers as $marker)
                    @php($day = \Carbon\CarbonImmutable::parse($marker['recorded'], 'UTC')->setTimezone($property->timezone)->toDateString())
                    <li><a href="{{ $show.'?'.http_build_query(array_replace($query, ['at'=>$day])) }}"><time datetime="{{ $day }}">{{ $day }}</time><strong>{{ $marker['label'] }}</strong><span>{{ __('time.'.$marker['kind']) }}</span></a></li>
                @empty
                    <li>{{ __('time.empty') }}</li>
                @endforelse
            </ol>
            <details @if(request()->has('history_cursor')) open @endif>
                <summary>{{ __('time.revisions') }}</summary>
                <ul class="tm-revisions">
                    @foreach($history as $event)
                        @php($day = $event->created_at->toImmutable()->setTimezone($property->timezone)->toDateString())
                        <li><a class="identity-link" href="{{ $show.'?'.http_build_query(array_replace($query, ['at'=>$day])) }}">{{ $day }} · #{{ $event->id }} · {{ __('building.history_action_'.$event->action) === 'building.history_action_'.$event->action ? str_replace('_', ' ', $event->action) : __('building.history_action_'.$event->action) }}</a></li>
                    @endforeach
                </ul>
                @if($hasOlder)<a class="identity-link" href="{{ $show.'?'.http_build_query($query + ['history_cursor'=>$history->last()->id]) }}">{{ __('time.earlier') }} →</a>@endif
            </details>
        </section>
    </div>

    <section class="tm-results" aria-label="{{ $other ? __('time.changes') : __('time.all') }}">
        <div class="tm-results-heading">
            <h2>{{ $other ? __('time.changes') : __('time.all') }} <span>{{ $results->total() }}</span></h2>
            @if($other)
                <div class="tm-export">
                    <a class="identity-button" href="{{ route('app.time-machine.export', [$organization,$property,$building->uuid] + $query) }}">{{ __('time.export') }}</a>
                    @if($pdfParts > 1)
                        <form action="{{ route('app.time-machine.export', [$organization,$property,$building->uuid]) }}" method="get" class="tm-parts">
                            @foreach($query as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                            <label>{{ __('time.part') }} <select name="page">@for($part=2; $part <= $pdfParts; $part++)<option value="{{ $part }}">{{ $part }} / {{ $pdfParts }}</option>@endfor</select></label>
                            <button class="identity-button" type="submit">{{ __('time.export') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
        @forelse($results as $entry)
            @if($other)
                @include('time-machine.comparison', ['pdf' => false])
            @else
                <article class="tm-comparison">@include('time-machine.record', ['row' => $entry, 'names' => $namesBefore])</article>
            @endif
        @empty
            <p>{{ $other ? __('time.diff_empty') : __('time.empty') }}</p>
        @endforelse
        {{ $results->links() }}
    </section>
</main>
@endsection
