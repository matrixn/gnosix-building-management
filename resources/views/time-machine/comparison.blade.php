@php
    $beforeFields = $entry['before']['fields'] ?? [];
    $afterFields = $entry['after']['fields'] ?? [];
    $changedFields = array_filter(array_unique([...array_keys($beforeFields), ...array_keys($afterFields)]), fn ($key) => ($beforeFields[$key] ?? null) !== ($afterFields[$key] ?? null));
@endphp
<article class="tm-comparison tm-{{ $entry['change'] }}">
    <p class="tm-change">{{ __('time.'.$entry['change']) }}</p>
    <div class="tm-sides">
        <div>
            <h4>{{ __('time.before') }} · {{ $state['valid'] }}</h4>
            @if($entry['before'])
                @include('time-machine.record', ['row' => $entry['before'], 'names' => $namesBefore])
            @else
                <p>{{ __('time.empty') }}</p>
            @endif
        </div>
        <div>
            <h4>{{ __('time.after') }} · {{ $other['valid'] }}</h4>
            @if($entry['after'])
                @include('time-machine.record', ['row' => $entry['after'], 'names' => $namesAfter])
            @else
                <p>{{ __('time.empty') }}</p>
            @endif
        </div>
    </div>
</article>
