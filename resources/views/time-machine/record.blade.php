@php
    $stateKey = in_array($row['state'], ['recorded','reviewed','open'], true) ? $row['state'].'_state' : $row['state'];
    $labelFor = fn ($key) => trans()->has('time.'.$key) ? __('time.'.$key) : str_replace('_', ' ', $key);
    $formatValue = function ($value) use ($labelFor, $names) {
        if (is_bool($value)) {
            return $value ? __('time.yes') : __('time.no');
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($item) => $names[$item] ?? $item, array_filter($value, 'is_scalar')));
        }
        return $names[is_string($value) ? $value : ''] ?? ($value === null || $value === '' ? __('time.unknown') : $value);
    };
@endphp
<div class="tm-record">
    <p class="tm-kind">{{ $labelFor($row['kind']) }} · {{ $labelFor($stateKey) }} · {{ $labelFor($row['support']) }}</p>
    <h3>{{ $row['label'] }}</h3>
    @if(!empty($row['fields']['competing_values']))<p class="tm-warning">{{ __('time.conflict') }}</p>@endif
    @if(!empty($row['future_fact']))<p class="tm-warning">{{ __('time.future_fact') }}</p>@endif
    @if(!empty($row['later']))<p class="tm-warning">{{ __('time.later') }}</p>@endif
    <dl class="tm-facts">
        @foreach(\Illuminate\Support\Arr::except($row['fields'], ['competing_values']) as $field => $value)
            <div @class(['tm-changed-field' => in_array($field, $changedFields ?? [], true)])>
                <dt>{{ $labelFor($field === 'source' ? 'source_field' : $field) }}</dt>
                <dd>{{ $formatValue($value) }}</dd>
            </div>
        @endforeach
        <div><dt>{{ __('time.effective') }}</dt><dd>{{ $row['effective'] ?? __('time.unknown') }}</dd></div>
        <div><dt>{{ __('time.recorded') }}</dt><dd>{{ $row['recorded'] }}</dd></div>
        @if($row['uploaded'])<div><dt>{{ __('time.uploaded') }}</dt><dd>{{ $row['uploaded'] }}</dd></div>@endif
        @if($row['reviewed'])<div><dt>{{ __('time.reviewed') }}</dt><dd>{{ $row['reviewed'] }}</dd></div>@endif
        <div><dt>{{ __('time.source') }}</dt><dd>@if($row['url'] && empty($pdf)) <a class="identity-link" href="{{ $row['url'] }}">{{ $row['source'] }}</a> @else {{ $row['source'] }} @endif</dd></div>
    </dl>
</div>
