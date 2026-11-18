@if($tmBuilding instanceof \App\Models\Building && $tmBuilding->property_id === $tmProperty->id && auth('web')->user()?->can('view', $tmProperty) && app(\App\Support\CapabilityService::class)->allows(auth('web')->user(), $organization, 'time.view'))
    <a class="identity-link" href="{{ route('app.time-machine.show', [$organization, $tmProperty, $tmBuilding->uuid]) }}">{{ __('time.title') }} ↗</a>
@endif
