<header class="trust-hero">
    <div>
        <span class="identity-kicker">{{ __('trust.title') }} · {{ $passport['building']['reference'] }}</span>
        <h1>{{ $passport['building']['name'] }}</h1>
        <p>{{ $passport['source_organization'] }} · {{ __('trust.captured', ['time' => \Illuminate\Support\Carbon::parse($passport['captured_at'])->locale(app()->getLocale())->isoFormat('lll').' UTC']) }}</p>
        <p class="trust-ref">{{ $passport['reference'] }}</p>
        <p>{{ __('building.construction_year') }} · {{ $passport['building']['construction_year'] ?? __('trust.unknown') }}
            @if($passport['building']['construction_year'])<span class="trust-chip">{{ __('trust.declared') }}</span>@endif
        </p>
        <p>{{ $passport['building']['classification'] ? __('building.preset_'.$passport['building']['classification']) : __('trust.unknown') }} · {{ $passport['building']['country'] ?? __('trust.unknown') }}</p>
    </div>
    <div class="trust-motif" aria-hidden="true"><i></i><i></i><i></i></div>
</header>
