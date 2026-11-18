<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>GNOSIX · {{ __('time.title') }}</title>
<style>
@page { margin: 38pt 42pt; }
body { color:#20282e; font:9pt "DejaVu Sans", sans-serif; }
header { background:#171f24; color:#fff; padding:20pt; border-bottom:4pt solid #aadc40; }
header p { margin:5pt 0; }
h1 { margin:6pt 0; font-size:22pt; }
h2 { font-size:14pt; }
h3,h4 { margin:4pt 0; }
.tm-comparison { border-top:1pt solid #bac6c8; margin:12pt 0; padding:8pt 0; }
.tm-change,.tm-kind { color:#34551f; font-weight:bold; }
.tm-sides > div { padding-left:9pt; border-left:2pt solid #b6ccaa; }
.tm-facts { margin:4pt 0 12pt; }
.tm-facts div { margin-bottom:3pt; }
dt,dd { display:inline; margin:0; }
dt { font-weight:bold; margin-right:4pt; }
.tm-warning,.tm-changed-field { background:#fcf0d8; }
</style>
</head>
<body>
<header><p>GNOSIX · {{ __('time.title') }}</p><h1>{{ $building->display_name ?: $building->name }}</h1><p>{{ $organization->name }} / {{ $property->name }}</p></header>
<p>{{ __('time.valid') }} <strong>{{ $state['valid'] }}</strong> · {{ __('time.known') }} {{ $state['knowledge'] }}</p>
<p>{{ __('time.compare') }} <strong>{{ $other['valid'] }}</strong> · {{ __('time.known') }} {{ $other['knowledge'] }}</p>
<p>{{ __('time.generated') }} {{ $generated }}</p>
<p>{{ __('time.page', ['page'=>$results->currentPage(), 'total'=>$results->lastPage()]) }} · {{ __('time.report_scope') }}</p>
<h2>{{ __('time.unverified') }}</h2>
<p>{{ __('time.missing') }} {{ __('time.limits') }} @if($state['partial'] || $other['partial']) {{ __('time.limit') }} @endif</p>
<h2>{{ __('time.changes') }} · {{ $results->total() }}</h2>
@php
    $namesBefore = array_column($state['rows'], 'label', 'key');
    $namesAfter = array_column($other['rows'], 'label', 'key');
@endphp
@forelse($results as $entry)
    @include('time-machine.comparison', ['pdf' => true])
@empty
    <p>{{ __('time.diff_empty') }}</p>
@endforelse
</body>
</html>
