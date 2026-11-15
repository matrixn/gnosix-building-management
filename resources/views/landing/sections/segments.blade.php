@php($segments = \App\Support\LandingContent::segments())
<section class="wrap segments" id="pentru-cine">
    <div class="section-heading"><span class="eyebrow">{{ __('ACELEAȘI CLĂDIRI. PERSPECTIVE DIFERITE.') }}</span><h2>{{ __('Locul în care') }}<br>{{ __('munca voastră se întâlnește.') }}</h2></div>
    <div class="segment-layout">
        <div class="segment-tabs" role="tablist" aria-orientation="vertical" aria-label="{{ __('Tipul proprietății') }}">
            @foreach ($segments as $key => $segment)<button id="tab-{{ $key }}" role="tab" aria-controls="segment-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-segment="{{ $key }}"><b>0{{ $loop->iteration }}</b> {{ $segment[0] }} <span>↗</span></button>@endforeach
        </div>
        <div class="segment-panels">
            @foreach ($segments as $key => $segment)
            <article id="segment-panel-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" tabindex="0" data-segment-panel="{{ $key }}" @if (! $loop->first) hidden @endif>
                <span class="eyebrow">{{ $segment[1] }}</span><h3>{{ $segment[2] }}</h3><p>{{ $segment[3] }}</p>
                <ul>@foreach ($segment[4] as $item)<li>{{ $item }}</li>@endforeach</ul><span class="fine">{{ __('Scenariu propus pentru etapa pilot') }}</span>
            </article>
            @endforeach
        </div>
    </div>
</section>
