<ul class="ops-catalog">
    @forelse($records as $record)
        @php
            $sourceBuilding = $sourceBuildings->get($record->building_id);
            $destination = match (true) {
                $record instanceof \App\Models\MaintenanceRecord => route('app.maintenance.'.$record->kind().'.show', [$organization,$record->uuid]),
                $record instanceof \App\Models\Asset => route('app.assets.show', [$organization,$record->uuid]),
                $record instanceof \App\Models\DnaRelation => route('app.dna.index', [$organization,'property'=>$record->property_id,'relation'=>$record->uuid]),
                $record instanceof \App\Models\Space => $url($record->building_id ? ['building'=>$record->building_id,'floor'=>null,'space'=>$record->id,'asset'=>null] : ['property'=>$record->property_id]),
                $sourceBuilding !== null => route('app.evidence.show', [$organization,$sourceBuilding->property_id,$sourceBuilding->uuid] + ($record instanceof \App\Models\Evidence\Version ? ['version'=>$record->id] : ['fact'=>$record->assertion_id])),
                default => null,
            };
        @endphp
        <li class="ops-record">
            @if($destination)<a href="{{ $destination }}">@endif
                {{ $record->title ?? $record->name ?? $record->original_name ?? $record->type_key ?? $record->id }}
                @if($record->reference)<small>{{ $record->reference }}</small>@endif
            @if($destination)</a>@endif
            @if($record instanceof \App\Models\Asset)
                <a class="identity-link" href="{{ $url(['asset'=>$record->id]).'#ops-explorer' }}">{{ __('ops.explore') }} ↗</a>
            @endif
            @if(in_array($metric, ['unassigned','priority'], true) && in_array($record->property_id, $editableItems, true))
                <a class="identity-link" href="{{ route('app.maintenance.work.edit', [$organization,$record->uuid]) }}">{{ __('ops.assign') }} ↗</a>
            @endif
        </li>
    @empty
        <li>{{ __('ops.empty') }}</li>
    @endforelse
</ul>
@include('operations.pagination', ['list'=>$records])
