<section class="ops-panel" aria-labelledby="ops-report-title">
    <h2 id="ops-report-title">{{ __('ops.reporting') }}</h2>
    <form class="ops-filter" method="post" action="{{ route('app.operations.export', $organization) }}">
        @csrf
        @foreach($filters as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <label>{{ __('ops.reporting') }} <select name="type">
            @foreach(['weekly'=>'weekly','monthly'=>'monthly','repeated'=>'repeated_report','asset_cost'=>'asset_cost','preventive'=>'preventive_report','quality'=>'quality'] as $type=>$label)
                @if($type === 'quality' ? $scope->capabilities['evidence'] : ($scope->capabilities['maintenance'] && ($type !== 'repeated' || $scope->capabilities['assets']) && ($type !== 'asset_cost' || ($scope->capabilities['assets'] && $canManage))))
                    <option value="{{ $type }}">{{ __('ops.'.$label) }}</option>
                @endif
            @endforeach
        </select></label>
        <label><span><input type="checkbox" name="use_dates" value="1"> {{ __('ops.report_use_dates') }}</span></label>
        <button type="submit" class="identity-button">{{ __('ops.request_report') }}</button>
    </form>
    @if($reports->isNotEmpty())
        <ul class="ops-catalog">
            @foreach($reports as $report)
                <li>{{ __('ops.'.(match($report->type) {'repeated'=>'repeated_report','preventive'=>'preventive_report',default=>$report->type})) }} · {{ __('ops.state_'.$report->state) }}
                    @if($report->state === 'ready') <a class="identity-link" href="{{ route('app.operations.download', [$organization,$report->id]) }}">{{ __('ops.download') }} ↗</a>@endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
