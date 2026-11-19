@extends('layouts.identity')
@section('title', __('ops.title'))
@section('content')
@php
    $base = route('app.operations.index', $organization);
    $url = fn (array $changes = []) => $base.'?'.http_build_query(array_filter(array_replace($filters, $changes), fn ($value) => $value !== null));
    $scopeName = $scope->asset?->name ?? $scope->space?->name ?? $scope->floor?->name ?? $scope->building?->name ?? $scope->property?->name ?? $scope->portfolio?->name ?? $organization->name;
    $names = array_filter(['group'=>$scope->portfolio?->name, 'property'=>$scope->property?->name, 'building'=>$scope->building?->name, 'floor'=>$scope->floor?->name, 'space'=>$scope->space?->name, 'asset'=>$scope->asset?->name]);
    $locations = ['property' => $scope->property?->id ?? $scope->building?->property_id ?? $scope->asset?->property_id, 'building' => $scope->building?->id, 'space' => $scope->space?->id, 'asset' => $scope->asset?->id];
    [$previousStart, $previousEnd] = $window->range(true);
    $priorFilters = ['from'=>$previousStart->setTimezone($organization->timezone)->toDateString(), 'to'=>$previousEnd->setTimezone($organization->timezone)->subDay()->toDateString()];
@endphp
<div class="ops-shell">
    <header class="ops-hero">
        <p>{{ $organization->name }}</p>
        <h1>{{ __('ops.title') }}</h1>
        <p>{{ __('ops.intro') }}</p>
        <nav aria-label="{{ __('ops.hierarchy') }}" class="ops-scope">
            <a href="{{ $base.'?'.http_build_query($window->filters()) }}">{{ __('ops.organization') }}</a>
            @foreach($names as $level => $name)
                @php($truncate = array_fill_keys(array_slice(['group','property','building','floor','space','asset'], array_search($level, ['group','property','building','floor','space','asset'], true) + 1), null))
                <a href="{{ $url($truncate) }}" @if($loop->last) aria-current="page" @endif>{{ $name }}</a>
            @endforeach
        </nav>
    </header>
    <form class="ops-filter ops-panel" method="get" action="{{ $base }}">
        @foreach(\Illuminate\Support\Arr::except($filters, ['from','to']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <label>{{ __('ops.from') }} <input name="from" type="date" required value="{{ $filters['from'] }}"></label>
        <label>{{ __('ops.to') }} <input name="to" type="date" required value="{{ $filters['to'] }}"></label>
        <button type="submit" class="identity-button identity-button--primary">{{ __('ops.apply') }}</button>
    </form>
    @if($errors->any())
        <ul class="ops-panel" role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif
    @if(session('status'))<p class="ops-panel" role="status">{{ session('status') }}</p>@endif
    @if($preference?->layout === 'explorer')
        @include('operations.explorer')
    @endif

    <section class="ops-panel" aria-labelledby="ops-priority">
        <h2 id="ops-priority">{{ __('ops.priority_heading') }}</h2>
        <div class="ops-focus">
            @forelse(collect(['critical_overdue','critical','priority','shared','reopened','repeated','conflicts','disputed','warranty'])->filter(fn ($key) => ($counts[$key] ?? 0) > 0)->take(6) as $key)
                <article>
                    <p class="ops-rule">{{ __('ops.rule') }} · {{ in_array($key, ['reopened','repeated']) ? $filters['from'].' – '.$filters['to'] : __('ops.now') }}</p>
                    <h3>{{ $counts[$key] }} · {{ __('ops.'.$key) }}</h3>
                    <p>{{ __('ops.'.$key.'_rule') }}</p>
                    <a class="identity-link" href="{{ $url(['metric'=>$key]).'#ops-records' }}">{{ __('ops.investigate') }} →</a>
                </article>
            @empty
                <p>{{ __('ops.empty') }}</p>
            @endforelse
        </div>
        <p class="ops-rule">{{ __('ops.limitations') }}</p>
    </section>
    @if($records)
        <section class="ops-panel" id="ops-records" aria-labelledby="ops-records-title">
            <h2 id="ops-records-title">{{ __('ops.'.$metric) }} · {{ $records->total() }}</h2>
            <p>{{ __('ops.'.$metric.'_rule') }}</p>
            @include('operations.records')
        </section>
    @endif

    <section aria-labelledby="ops-current">
        <h2 id="ops-current">{{ __('ops.now') }} · {{ $scopeName }}</h2>
        @php($mainMetrics = array_intersect_key($counts, array_flip(['open','critical','unassigned','overdue','upcoming','attention','observed_spaces','conflicts','completed'])))
        <div class="ops-metrics">
            @foreach($mainMetrics as $key => $count) @include('operations.metric') @endforeach
        </div>
        @if(array_diff_key($counts, $mainMetrics))
            <details class="ops-panel ops-more">
                <summary>{{ __('ops.additional') }}</summary>
                <div class="ops-metrics">
                    @foreach(array_diff_key($counts, $mainMetrics) as $key => $count) @include('operations.metric') @endforeach
                </div>
            </details>
        @endif
    </section>
    @if($preference?->layout !== 'explorer')
        @include('operations.explorer')
    @endif
    <div class="ops-split">
        @if($current)
        <section class="ops-panel" aria-labelledby="ops-activity">
            <h2 id="ops-activity">{{ __('ops.by_window') }}</h2>
            <table class="ops-table">
                <thead><tr><th scope="col">{{ __('ops.period') }}</th><th scope="col">{{ __('ops.reported') }}</th><th scope="col">{{ __('ops.completed') }}</th></tr></thead>
                <tbody><tr><th scope="row">{{ __('ops.period') }}</th><td><a class="identity-link" href="{{ $url(['metric'=>'reported']).'#ops-records' }}">{{ $current['reported'] }}</a></td><td><a class="identity-link" href="{{ $url(['metric'=>'completed']).'#ops-records' }}">{{ $current['completed'] }}</a></td></tr>
                <tr><th scope="row">{{ __('ops.prior') }}</th><td><a class="identity-link" href="{{ $url($priorFilters + ['metric'=>'reported']).'#ops-records' }}">{{ $previous['reported'] }}</a></td><td><a class="identity-link" href="{{ $url($priorFilters + ['metric'=>'completed']).'#ops-records' }}">{{ $previous['completed'] }}</a></td></tr></tbody>
            </table>
        </section>
        @endif
        @if($preventive)
        <section class="ops-panel">
            <h2>{{ __('ops.preventive') }}</h2>
            <dl class="ops-def"><div><dt>{{ __('ops.scheduled') }}</dt><dd>{{ $preventive['scheduled'] }}</dd></div><div><dt>{{ __('ops.on_time') }}</dt><dd>{{ $preventive['on_time'] }}</dd></div><div><dt>{{ __('ops.late') }}</dt><dd>{{ $preventive['late'] }}</dd></div></dl>
            <p>{{ __('ops.prev_rule') }}</p>
            <a class="identity-link" href="{{ $url(['metric'=>'preventive']).'#ops-records' }}">{{ __('ops.details') }} →</a>
        </section>
        @endif
    </div>
    @if($cost['available'] || $priorCost['available'])
        <section class="ops-panel" aria-labelledby="ops-cost">
            <h2 id="ops-cost">{{ __('ops.costs') }}</h2>
            <p>{{ __('ops.cost_rule') }}</p>
            <div class="ops-split">
                @foreach(['period'=>$cost, 'prior'=>$priorCost] as $label => $block)
                    <div><h3>{{ __('ops.'.$label) }}</h3>@include('operations.cost', ['block'=>$block])
                        <a class="identity-link" href="{{ $url(($label === 'prior' ? $priorFilters : []) + ['metric'=>'cost_orders']).'#ops-records' }}">{{ __('ops.details') }} →</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
    @if($recent->isNotEmpty())
        <section class="ops-panel">
            <h2>{{ __('ops.recent') }}</h2>
            <ul class="ops-catalog">
                @foreach($recent as $work)
                    <li><a href="{{ route('app.maintenance.work.show', [$organization,$work->uuid]) }}">{{ $work->title }} <small>{{ $work->reference }} · {{ $work->resolved_at->setTimezone($organization->timezone)->format('d.m.Y H:i') }}</small></a></li>
                @endforeach
            </ul>
        </section>
    @endif
    @if($scope->capabilities['maintenance'] || $scope->capabilities['evidence'])
        @include('operations.reporting')
    @endif

    <details class="ops-panel">
        <summary>{{ __('ops.preferences') }}</summary>
        <form class="ops-filter" method="post" action="{{ route('app.operations.preferences', $organization) }}">
            @csrf
            @foreach($filters as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <label>{{ __('ops.preferences') }} <select name="layout"><option value="overview">{{ __('ops.overview') }}</option><option value="explorer" @selected($preference?->layout === 'explorer')>{{ __('ops.explorer') }}</option></select></label>
            @if($canManage)<label><span><input type="checkbox" name="weekly_digest" value="1" @checked($preference?->weekly_digest)> {{ __('ops.weekly_digest') }}</span></label>@endif
            <button type="submit" class="identity-button">{{ __('ops.save') }}</button>
        </form>
    </details>
</div>
@endsection
