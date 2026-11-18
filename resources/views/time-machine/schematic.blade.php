@php
    $structure = collect($side['rows'])->reject(fn ($row) => in_array($row['state'], ['archived','deleted'], true));
    $floors = $structure->where('kind','floor')->sortByDesc(fn ($floor) => $floor['fields']['position'] ?? -1);
    $rooms = $structure->where('kind','space')->groupBy(fn ($room) => $room['fields']['floor'] ?? '');
    $bands = $floors->take(12)->map(fn ($floor) => ['id'=>str($floor['key'])->after(':')->value(), 'label'=>$floor['label']])->values();
    if ($rooms->has('')) {
        $bands->push(['id'=>'','label'=>__('time.unknown')]);
    }
@endphp
<div class="tm-model">
    <h3><time datetime="{{ $side['valid'] }}">{{ $side['valid'] }}</time></h3>
    @if($bands->isNotEmpty())
        <div class="tm-levels">
            @foreach($bands as $band)
                <details @if($loop->first) open @endif class="tm-level">
                    <summary><strong>{{ $band['label'] }}</strong><span>{{ $rooms->get($band['id'], collect())->count() }} · {{ __('time.space') }}</span></summary>
                    <ul>@foreach($rooms->get($band['id'], collect())->take(18) as $room)<li>{{ $room['label'] }}</li>@endforeach</ul>
                </details>
            @endforeach
        </div>
        <small>{{ __('time.level_visibility') }}</small>
    @else
        <p>{{ __('time.no_spatial') }}</p>
    @endif
</div>
