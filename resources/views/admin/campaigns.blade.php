@extends('admin.layout')
@section('title', 'Campanii')
@section('content')
@php
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');
    $rate = fn ($passed, $all) => $all ? number_format((int) $passed * 100 / (int) $all, 1, ',', '.').'%' : '—';
@endphp
<header class="page-heading">
    <h1>Campanii</h1>
    <p>Vezi ce linkuri UTM au adus vizite, solicitări de contact și conturi noi.</p>
</header>
<form class="campaign-filter" action="{{ route('admin.campaigns') }}" method="get">
    <label>Caută în etichete UTM <input type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Ex. uk_founding_partners_2026"></label>
    <label>Sortează după <select name="sort">
        <option value="recent" @selected($sort === 'recent')>Ultima vizită</option>
        <option value="visits" @selected($sort === 'visits')>Cele mai multe vizite</option>
        <option value="conversions" @selected($sort === 'conversions')>Cele mai multe vizite convertite</option>
    </select></label>
    <button class="button" type="submit">Aplică</button>
    @if($search !== '' || $sort !== 'recent')<a class="button ghost" href="{{ route('admin.campaigns') }}">Resetează</a>@endif
</form>
<dl class="campaign-metrics" aria-label="Totaluri pentru linkurile filtrate">
    <div><dt>Linkuri</dt><dd>{{ $number($totals->links) }}</dd></div>
    <div><dt>Vizite</dt><dd>{{ $number($totals->visits) }}</dd></div>
    <div><dt>Contacte</dt><dd>{{ $number($totals->contacts) }}</dd></div>
    <div><dt>Conturi create</dt><dd>{{ $number($totals->registrations) }}</dd></div>
    <div class="campaign-rate"><dt>Rată de conversie</dt><dd>{{ $rate($totals->converted_visits, $totals->visits) }}</dd></div>
</dl>
<p class="fine campaign-explanation">Conversia este o vizită cu cel puțin un formular de contact trimis cu succes sau un cont creat. Dacă apar ambele, vizita intră o singură dată în rată. Atribuirea folosește ultimul link etichetat din aceeași sesiune, în maximum două ore.</p>

<section aria-labelledby="campaign-links">
    <h2 id="campaign-links">Linkuri înregistrate</h2>
    @forelse($links as $link)
        <article class="panel campaign-entry">
            <div class="campaign-entry-heading">
                <h3>{{ $link->tags['utm_campaign'] }}</h3>
                <time datetime="{{ \Illuminate\Support\Carbon::parse($link->last_seen_at, 'UTC')->toIso8601String() }}">Ultima vizită: {{ \Illuminate\Support\Carbon::parse($link->last_seen_at, 'UTC')->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}</time>
            </div>
            <dl class="campaign-tags">
                @foreach(['utm_source'=>'Sursă', 'utm_medium'=>'Canal', 'utm_content'=>'Conținut', 'utm_term'=>'Termen', 'utm_id'=>'ID campanie'] as $key => $label)
                    @isset($link->tags[$key])<div><dt>{{ $label }}</dt><dd>{{ $link->tags[$key] }}</dd></div>@endisset
                @endforeach
            </dl>
            <div class="campaign-url"><code>{{ $link->url }}</code><button type="button" class="button ghost small" data-copy-campaign="{{ $link->url }}">Copiază linkul</button></div>
            <dl class="campaign-metrics campaign-row-metrics">
                <div><dt>Vizite</dt><dd>{{ $number($link->visits) }}</dd></div>
                <div><dt>Contacte</dt><dd>{{ $number($link->contacts) }}</dd></div>
                <div><dt>Conturi create</dt><dd>{{ $number($link->registrations) }}</dd></div>
                <div><dt>Vizite convertite</dt><dd>{{ $number($link->converted_visits) }}</dd></div>
                <div class="campaign-rate"><dt>Conversie</dt><dd>{{ $rate($link->converted_visits, $link->visits) }}</dd></div>
            </dl>
        </article>
    @empty
        <p class="empty-state">{{ $search !== '' ? 'Niciun link nu corespunde căutării.' : 'Linkurile apar aici după prima vizită cu un parametru utm_campaign valid.' }}</p>
    @endforelse
    @if($links->hasPages())
        <nav class="campaign-pagination" aria-label="Paginile campaniilor">
            @if($links->previousPageUrl())<a class="button ghost" href="{{ $links->previousPageUrl() }}">← Anterioară</a>@endif
            <span>Pagina {{ $links->currentPage() }} din {{ $links->lastPage() }}</span>
            @if($links->nextPageUrl())<a class="button ghost" href="{{ $links->nextPageUrl() }}">Următoarea →</a>@endif
        </nav>
    @endif
</section>
<p class="fine campaign-explanation">Vizitele sunt estimări. Sistemele de scanare a linkurilor pot genera vizite, iar conversiile de pe alt dispozitiv sau după expirarea sesiunii pot rămâne neatribuite. Datele sunt colectate începând cu activarea modulului.</p>
<p class="fine" data-campaign-copy-status role="status"></p>
@endsection
