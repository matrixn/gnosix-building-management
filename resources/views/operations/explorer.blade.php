<section class="ops-panel" id="ops-explorer" aria-labelledby="ops-explorer-title">
    <h2 id="ops-explorer-title">{{ __('ops.hierarchy') }}</h2>
    <p>{{ $scopeName }}</p>
    <div class="ops-links">
        @if($scope->building)
            @if($scope->capabilities['rooms'])
                <a class="identity-link" href="{{ route('app.declarations.show', [$organization,$scope->building->property_id,$scope->building->uuid]) }}">{{ __('ops.studio') }} ↗</a>
                <a class="identity-link" href="{{ $url(['view'=>'spaces']).'#ops-explorer' }}">{{ __('ops.space') }} →</a>
            @endif
            @if($scope->capabilities['history'])
                <a class="identity-link" href="{{ route('app.time-machine.show', [$organization,$scope->building->property_id,$scope->building->uuid,'at'=>$filters['from'],'compare'=>$filters['to']]) }}">{{ __('ops.history') }} ↗</a>
            @endif
            @if($scope->capabilities['evidence'])
                <a class="identity-link" href="{{ route('app.evidence.show', [$organization,$scope->building->property_id,$scope->building->uuid]) }}">{{ __('ops.evidence') }} ↗</a>
            @endif
        @endif
        @if($scope->capabilities['assets'] && ($scope->building || $scope->property))
            <a class="identity-link" href="{{ $url(['view'=>'assets']).'#ops-explorer' }}">{{ __('ops.asset') }} →</a>
        @endif
        @if($scope->capabilities['maintenance'])
            <a class="identity-link" href="{{ route('app.maintenance.index', [$organization] + \Illuminate\Support\Arr::only($filters, ['group','property','building','space','asset'])) }}">{{ __('ops.maintenance') }} ↗</a>
        @endif
        @if($canManage)
            <a class="identity-link" href="{{ route('app.maintenance.plan.create', [$organization] + ($scope->asset && $scope->building && $scope->asset->building_id !== $scope->building->id ? ['property'=>$locations['property'],'asset'=>$scope->asset->id] : $locations)) }}">{{ __('ops.schedule') }} ↗</a>
            <a class="identity-link" href="{{ route('app.field.manage', [$organization,'property'=>$locations['property']]) }}">{{ __('ops.invite') }} ↗</a>
        @endif
    </div>
    @if($portfolios?->count())
        <h3>{{ __('ops.portfolio') }}</h3>
        <ul class="ops-catalog">
            @foreach($portfolios as $portfolio)<li><a href="{{ $url(['group'=>$portfolio->id]).'#ops-explorer' }}">{{ $portfolio->name }} <span>→</span></a></li>@endforeach
        </ul>
        @include('operations.pagination', ['list'=>$portfolios])
    @endif
    @if($items)
        <h3>{{ __('ops.'.$items[0]) }}</h3>
        <ul class="ops-catalog">
            @forelse($items[1] as $item)
                <li><a href="{{ $url([$items[0]=>$item->id]).'#ops-explorer' }}">{{ $item->name }} <span>→</span></a></li>
            @empty
                <li>{{ __('ops.empty') }}</li>
            @endforelse
        </ul>
        @include('operations.pagination', ['list'=>$items[1]])
    @endif
    @if($scope->asset)
        <p>{{ __('asset.status_'.$scope->asset->status) }}</p>
        <div class="ops-links">
            <a class="identity-link" href="{{ route('app.assets.show', [$organization,$scope->asset->uuid]) }}">{{ __('ops.passport') }} ↗</a>
            @if($scope->capabilities['dna'])<a class="identity-button" href="{{ $url(['simulate'=>1]).'#ops-impact' }}">{{ __('ops.simulate') }}</a>@endif
        </div>
    @endif
    @if($impact)
        <div id="ops-impact" class="ops-callout">
            <h3>{{ __('ops.potential') }} · {{ count($impact['potential_spaces']) }}</h3>
            <p>{{ __('ops.sim_context') }} @if($impact['truncated']) {{ __('ops.partial') }} @endif</p>
            <ul>@foreach(array_slice($impact['potential_spaces'], 0, 12) as $affected)<li>{{ $affected['name'] }}</li>@endforeach</ul>
            <a class="identity-link" href="{{ route('app.dna.index', [$organization,'property'=>$scope->asset->property_id,'focus'=>'asset:'.$scope->asset->uuid,'simulate'=>1]) }}">{{ __('ops.dna') }} ↗</a>
        </div>
    @endif
</section>
