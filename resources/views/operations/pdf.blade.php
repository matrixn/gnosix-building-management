<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    body { margin: 22px; font: 11px "DejaVu Sans", sans-serif; color: #1e2924; }
    header { padding: 18px; background: #1a2821; color: #fff; border-bottom: 5px solid #9bc952; }
    header h1 { margin: 4px 0 8px; font-size: 22px; }
    h2 { font-size: 15px; margin-top: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 6px; border-bottom: 1px solid #ccd5ca; text-align: left; vertical-align: top; }
    p { margin: 6px 0; }
    .ops-table-wrap { overflow-wrap: break-word; }
</style>
</head>
<body>
<header>
    <p>GNOSIX · {{ $scope->organization->name }}</p>
    <h1>{{ __('ops.'.(match($report->type) {'repeated'=>'repeated_report','preventive'=>'preventive_report',default=>$report->type})) }}</h1>
    <p>{{ $scope->asset?->name ?? $scope->space?->name ?? $scope->floor?->name ?? $scope->building?->name ?? $scope->property?->name ?? $scope->portfolio?->name ?? $scope->organization->name }} · {{ $window->filters()['from'] }} – {{ $window->filters()['to'] }}</p>
    <p>{{ __('ops.generated') }} {{ now()->toIso8601String() }} · {{ $scope->organization->timezone }}</p>
</header>
<p>{{ __('ops.limitations') }}</p>
@if($activity)
    <h2>{{ __('ops.by_window') }}</h2>
    <table><thead><tr><th>{{ __('ops.period') }}</th><th>{{ __('ops.reported') }}</th><th>{{ __('ops.completed') }}</th></tr></thead>
    <tbody><tr><th>{{ __('ops.period') }}</th><td>{{ $activity['reported'] }}</td><td>{{ $activity['completed'] }}</td></tr><tr><th>{{ __('ops.prior') }}</th><td>{{ $prior['reported'] }}</td><td>{{ $prior['completed'] }}</td></tr></tbody></table>
@endif
@if($cost && $cost['available'])
    <h2>{{ __('ops.costs') }}</h2><p>{{ __('ops.cost_rule') }}</p>
    @include('operations.cost', ['block'=>$cost])
@endif
@if($assetCosts->isNotEmpty())
    <h2>{{ __('ops.asset_cost') }}</h2>
    <table>
        <thead><tr><th>{{ __('ops.ref') }}</th><th>{{ __('ops.currency') }}</th><th>{{ __('ops.labor') }}</th><th>{{ __('ops.materials') }}</th><th>{{ __('ops.external') }}</th></tr></thead>
        <tbody>
            @foreach($assetCosts->take(30) as $row)
                <tr><th>{{ $row->reference }}</th><td>{{ $row->currency }}</td>@foreach(['labor','materials','external'] as $key)<td>{{ $row->{$key} === null ? __('ops.unknown') : number_format((float) $row->{$key}, 2, '.', ' ') }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
    @if($assetCosts->count() > 30)<p>{{ __('ops.asset_cost_partial') }}</p>@endif
@endif
@if($preventive)
    <h2>{{ __('ops.preventive') }}</h2>
    <p>{{ __('ops.scheduled') }} {{ $preventive['scheduled'] }} · {{ __('ops.on_time') }} {{ $preventive['on_time'] }} · {{ __('ops.late') }} {{ $preventive['late'] }}</p>
    <p>{{ __('ops.prev_rule') }}</p>
@endif
<p>{{ __('ops.samples') }}</p>
@foreach($counts as $key => $count)
    <h2>{{ __('ops.'.$key) }} · {{ $count }}</h2>
    <p>{{ __('ops.'.$key.'_rule') }}</p>
    @if($records[$key]->isNotEmpty())
        <table><tbody>@foreach($records[$key] as $sample)<tr><th>{{ $sample['reference'] }}</th><td>{{ $sample['name'] }}</td></tr>@endforeach</tbody></table>
    @endif
@endforeach
</body>
</html>
