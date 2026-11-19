<div class="ops-table-wrap" tabindex="0" role="region" aria-label="{{ __('ops.costs') }}">
<table class="ops-table">
    <thead><tr><th scope="col">{{ __('ops.currency') }}</th><th scope="col">{{ __('ops.labor') }}</th><th scope="col">{{ __('ops.materials') }}</th><th scope="col">{{ __('ops.external') }}</th></tr></thead>
    <tbody>
        @forelse($block['rows'] as $row)
            <tr><th scope="row">{{ $row->currency }}</th>
                @foreach(['labor','materials','external'] as $key)<td>{{ $row->{$key} === null ? __('ops.unknown') : number_format((float)$row->{$key}, 2, '.', ' ') }}</td>@endforeach
            </tr>
        @empty
            <tr><td colspan="4">{{ __('ops.empty') }}</td></tr>
        @endforelse
    </tbody>
</table>
</div>
@if($block['incomplete'] !== null)<p>{{ __('ops.incomplete') }} · {{ $block['incomplete'] }}</p>@endif
