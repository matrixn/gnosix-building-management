<a class="ops-panel ops-metric" href="{{ $url(['metric'=>$key]).'#ops-records' }}">
    <strong>{{ $count }}</strong>
    <span>{{ __('ops.'.$key) }}</span>
    <small>{{ __('ops.'.$key.'_rule') }}</small>
</a>
