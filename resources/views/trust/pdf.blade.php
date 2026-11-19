<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('trust.title') }} · {{ $passport['reference'] }}</title>
    <style>
        @page { margin: 37px 42px; }
        body { font: 10px DejaVu Sans, sans-serif; line-height: 1.5; color: #1c2724; }
        header { background: #182623; color: #fff; padding: 20px; }
        header h1 { font-size: 24px; margin: 4px 0; }
        header strong { color: #c8f783; }
        h2 { font-size: 15px; margin: 19px 0 6px; color: #284a39; page-break-after: avoid; }
        p { margin: 5px 0; overflow-wrap: break-word; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d5e4dc; padding: 5px; text-align: left; word-wrap: break-word; }
        th { background: #ecf3eb; }
        .meta { color: #52655e; font-size: 9px; }
    </style>
</head>
<body>
<header>
    <strong>GNOSIX / {{ __('trust.title') }}</strong>
    <h1>{{ $passport['building']['name'] }}</h1>
    <p>{{ $passport['reference'] }} · {{ $passport['building']['reference'] }}</p>
    <p>{{ $passport['source_organization'] }} · {{ __('trust.captured', ['time' => $passport['captured_at']]) }}</p>
</header>
<p>{{ __('building.construction_year') }} · {{ $passport['building']['construction_year'] ?? __('trust.unknown') }}</p>
<p>{{ __('building.country') }} · {{ $passport['building']['country'] ?? __('trust.unknown') }}</p>
<p>{{ __('trust.limitations') }}</p>
<h2>{{ __('trust.ratio') }}</h2>
<table>
    @foreach($passport['coverage'] as $key => [$count, $total])
        <tr><th>{{ __('trust.'.$key) }}</th><td>{{ $total ? "$count / $total" : __('trust.no_denominator') }}</td><td>{{ __('trust.formula_'.$key) }}</td></tr>
    @endforeach
</table>
<h2>{{ __('trust.readiness') }}</h2>
@forelse($passport['findings'] as $finding)
    <p>{{ $finding['count'] }} · {{ __('trust.'.$finding['code']) }}</p>
@empty
    <p>{{ __('trust.no_findings') }}</p>
@endforelse
@foreach($passport['sections'] as $key => $section)
    <h2>{{ __('trust.'.$key) }}</h2>
    @if(isset($section['items']))
        <p>{{ __('trust.records', ['shown' => count($section['items']), 'total' => $section['total']]) }}</p>
        @if($section['unavailable'] ?? 0)<p>{{ __('trust.unavailable_versions', ['count' => $section['unavailable']]) }}</p>@endif
        <table>
            @foreach($section['items'] as $row)
                <tr>
                    @switch($key)
                        @case('documents')<td>{{ $row['original_name'] }}</td><td>{{ __('trust.version', ['number' => $row['number']]) }}</td><td>{{ $row['sha256'] }}</td>@break
                        @case('assets')<td>{{ $row['name'] }}</td><td>{{ $row['reference'] }}</td><td>{{ $row['warranty_end'] ? __('trust.warranty_until', ['date' => $row['warranty_end']]) : __('trust.warranty_unknown') }}</td>@break
                        @case('maintenance')<td>{{ $row['reference'] }}</td><td>{{ __('maintenance.state_'.$row['state']) }}</td><td>{{ $row['title'] ?? '' }}</td>@break
                        @case('dna')<td>{{ $row['type_key'] }}</td><td>{{ $row['state'] }}</td>@break
                        @case('history')<td>{{ $row['action'] }}</td><td>{{ $row['created_at'] }}</td>@break
                    @endswitch
                </tr>
            @endforeach
        </table>
    @endif
    @if($key === 'evidence')
        <p>{{ __('trust.documented') }} · {{ $section['documented'] }} | {{ __('trust.reviewed') }} · {{ $section['status']['confirmed'] ?? 0 }} | {{ __('trust.disputed') }} · {{ $section['status']['disputed'] ?? 0 }} | {{ __('trust.open_conflicts') }} · {{ $section['conflicts'] }}</p>
    @endif
    @if($key === 'maintenance')
        <p>@foreach(['open_orders', 'open_issues', 'resolved'] as $metric){{ __('trust.'.$metric) }} · {{ $section[$metric] }} @if(!$loop->last) | @endif @endforeach</p>
    @endif
@endforeach
@if(isset($passport['handover']))
    <h2>{{ __('trust.checklist') }}</h2>
    @foreach($passport['handover']['checks'] as $check)<p>✓ {{ __('trust.check_'.$check) }}</p>@endforeach
    @if($passport['handover']['responsibilities'])<h2>{{ __('trust.responsibilities') }}</h2><p>{{ $passport['handover']['responsibilities'] }}</p>@endif
@endif
<p class="meta">{{ __('trust.manifest_note', ['schema' => $passport['schema']]) }}</p>
</body>
</html>
