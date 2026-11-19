@php
    $internal = $internal ?? false;
    $recipientFlow = $recipientFlow ?? null;
@endphp
<p class="trust-notice">{{ __('trust.limitations') }}</p>
@if($passport['coverage'])
<section class="trust-panel" aria-label="{{ __('trust.ratio') }}">
    <h2>{{ __('trust.ratio') }}</h2>
    <div class="trust-coverage">
        @foreach($passport['coverage'] as $key => [$count, $total])
            <div class="identity-card trust-metric">
                <h3>{{ __('trust.'.$key) }}</h3>
                @if($total)
                    <p class="trust-metric-value">{{ $count }} <span>/ {{ $total }}</span></p>
                    <meter min="0" max="{{ $total }}" value="{{ $count }}" aria-label="{{ __('trust.'.$key) }}">{{ $count }} / {{ $total }}</meter>
                @else
                    <p>{{ __('trust.no_denominator') }}</p>
                @endif
                <p class="field-help">{{ __('trust.formula_'.$key) }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif
<section class="identity-card trust-panel" aria-label="{{ __('trust.readiness') }}">
    <h2>{{ __('trust.readiness') }}</h2>
    <ul class="trust-list trust-findings">
        @forelse($passport['findings'] as $finding)
            <li>
                <strong>{{ __('trust.'.$finding['code']) }}</strong>
                <span class="trust-chip">{{ $finding['count'] }}</span>
                @if($internal)
                    @php
                        $record = $finding['record'] ?? null;
                        $origin = match ($finding['section']) {
                            'evidence' => route('app.evidence.show', [$organization, $property, $building->uuid] + ($record ? [($finding['code'] === 'unavailable' ? 'version' : 'fact') => $record] : [])),
                            'assets' => $record ? route('app.assets.show', [$organization, $record]) : route('app.assets.index', [$organization, 'building' => $building->id]),
                            'maintenance' => $record ? route('app.maintenance.work.show', [$organization, $record]) : route('app.maintenance.index', [$organization, 'building' => $building->id]),
                        };
                    @endphp
                    <a class="identity-link" href="{{ $origin }}">{{ __('trust.source') }} ↗</a>
                @else
                    <a class="identity-link" href="#trust-section-{{ $finding['section'] === 'evidence' ? 'evidence' : ($finding['code'] === 'isolated_system' ? 'dna' : $finding['section']) }}">{{ __('trust.source') }} ↓</a>
                @endif
            </li>
        @empty
            <li>{{ __('trust.no_findings') }}</li>
        @endforelse
    </ul>
</section>
<section class="trust-register" aria-label="{{ __('trust.title') }}">
    @foreach($passport['sections'] as $key => $section)
        <details id="trust-section-{{ $key }}" class="identity-card trust-register-item" @if($loop->first || $key === 'evidence') open @endif>
            <summary>{{ __('trust.'.$key) }} @if(isset($section['total']))<span class="trust-chip">{{ $section['total'] }}</span>@endif</summary>
            @if($key === 'evidence')
                <dl class="trust-values">
                    <div><dt>{{ __('trust.documented') }}</dt><dd>{{ $section['documented'] }}</dd></div>
                    <div><dt>{{ __('trust.reviewed') }}</dt><dd>{{ $section['status']['confirmed'] ?? 0 }}</dd></div>
                    <div><dt>{{ __('trust.disputed') }}</dt><dd>{{ $section['status']['disputed'] ?? 0 }}</dd></div>
                    <div><dt>{{ __('trust.open_conflicts') }}</dt><dd>{{ $section['conflicts'] }}</dd></div>
                </dl>
            @endif
            @if($key === 'maintenance')
                <dl class="trust-values">
                    @foreach(['open_orders', 'open_issues', 'resolved'] as $metric)<div><dt>{{ __('trust.'.$metric) }}</dt><dd>{{ $section[$metric] }}</dd></div>@endforeach
                </dl>
            @endif
            @if($key === 'maintenance' && $section['issues'])
                <h3>{{ __('trust.open_issues') }}</h3>
                <ul class="trust-list">
                    @foreach($section['issues'] as $issue)
                        <li>
                            <strong>{{ $issue['reference'] }} @isset($issue['title']) · {{ $issue['title'] }} @endisset</strong>
                            <span>{{ __('maintenance.state_'.$issue['state']) }}</span>
                            @if($internal)<a class="identity-link" href="{{ route('app.maintenance.issue.show', [$organization, $issue['uuid']]) }}">{{ __('trust.source') }} ↗</a>@endif
                        </li>
                    @endforeach
                </ul>
                <h3>{{ __('trust.open_orders') }} / {{ __('trust.resolved') }}</h3>
            @endif
            @if(isset($section['items']))
                <p class="field-help">{{ __('trust.records', ['shown' => count($section['items']), 'total' => $section['total']]) }}</p>
                @if($section['unavailable'] ?? 0)<p class="trust-notice">{{ __('trust.unavailable_versions', ['count' => $section['unavailable']]) }}</p>@endif
                <ul class="trust-list">
                    @foreach($section['items'] as $row)
                        <li>
                            @switch($key)
                                @case('documents')
                                    <strong>{{ $row['original_name'] }}</strong><span>{{ __('trust.version', ['number' => $row['number']]) }}</span>
                                    <span class="trust-chip">{{ __('trust.documented') }}</span>
                                    @if($recipientFlow)<a class="identity-link" href="{{ route('handover.document', [$recipientFlow, $row['id']]) }}">{{ __('trust.document_download') }} ↓</a>@endif
                                    @if($internal)<a class="identity-link" href="{{ route('app.evidence.show', [$organization, $property, $building->uuid, 'version' => $row['id']]) }}">{{ __('trust.source') }} ↗</a>@endif
                                    @break
                                @case('assets')
                                    <strong>{{ $row['name'] }}</strong><span>{{ $row['reference'] }}</span>
                                    @if($row['is_system'])<span class="trust-chip">{{ __('trust.system') }}</span>@endif
                                    <span>{{ $row['warranty_end'] ? __('trust.warranty_until', ['date' => $row['warranty_end']]) : __('trust.warranty_unknown') }}</span>
                                    @if($internal)<a class="identity-link" href="{{ route('app.assets.show', [$organization, $row['uuid']]) }}">{{ __('trust.source') }} ↗</a>@endif
                                    @break
                                @case('maintenance')
                                    <strong>{{ $row['reference'] }} @isset($row['title']) · {{ $row['title'] }} @endisset</strong><span>{{ __('maintenance.state_'.$row['state']) }} · {{ __('maintenance.severity_'.$row['priority']) }}</span>
                                    @if($internal)<a class="identity-link" href="{{ route('app.maintenance.work.show', [$organization, $row['uuid']]) }}">{{ __('trust.source') }} ↗</a>@endif
                                    @break
                                @case('dna')
                                    <strong>{{ $row['type_key'] }}</strong><span>{{ $row['state'] }}</span>
                                    @if($internal)<a class="identity-link" href="{{ route('app.dna.graph', [$organization, $property, 'focus' => 'building:'.$building->uuid]) }}">{{ __('trust.source') }} ↗</a>@endif
                                    @break
                                @case('history')
                                    <strong>{{ $row['action'] }}</strong><time>{{ $row['created_at'] }}</time>
                                    @if($internal)<a class="identity-link" href="{{ route('app.time-machine.show', [$organization, $property, $building->uuid]) }}">{{ __('trust.source') }} ↗</a>@endif
                                    @break
                            @endswitch
                        </li>
                    @endforeach
                </ul>
            @endif
        </details>
    @endforeach
</section>
@if(isset($passport['handover']))
    <section class="identity-card trust-panel" aria-label="{{ __('trust.checklist') }}">
        <h2>{{ __('trust.checklist') }}</h2>
        <ul class="trust-list">@foreach($passport['handover']['checks'] as $check)<li><span aria-hidden="true">✓</span> {{ __('trust.check_'.$check) }}</li>@endforeach</ul>
        @if($passport['handover']['responsibilities'])
            <h3>{{ __('trust.responsibilities') }}</h3><p class="trust-note">{{ $passport['handover']['responsibilities'] }}</p>
        @endif
    </section>
@endif
