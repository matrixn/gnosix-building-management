<details class="trust-exports">
    <summary>{{ __('trust.exports') }}</summary>
    <p class="field-help">{{ __('trust.integrity') }}</p>
    <ul>
        @foreach(['pdf' => 'pdf', 'json' => 'json', 'zip' => 'archive'] as $format => $label)
            <li><a class="identity-link" href="{{ route($exportRoute, [...$exportParams, $format]) }}">{{ __('trust.'.$label) }} ↓</a></li>
        @endforeach
    </ul>
</details>
