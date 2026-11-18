@extends('admin.layout')
@section('title', 'Trafic și campanii')
@section('content')
@php
    $countryName = static function (string $code): string {
        if ($code === \App\Support\LandingCountry::UNKNOWN) {
            return 'Țară necunoscută';
        }

        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('und_'.$code, 'ro') : false;

        return $name && $name !== $code ? $name.' ('.$code.')' : $code;
    };
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');
    $rate = fn ($passed, $all) => $all ? number_format((int) $passed * 100 / (int) $all, 1, ',', '.').'%' : '—';
@endphp
<header class="page-heading">
    <h1>Trafic și campanii</h1>
    <p>Vezi de unde vin vizitele pe pagina publică și ce linkuri duc la solicitări de contact sau conturi noi.</p>
</header>
<form class="campaign-filter" action="{{ route('admin.campaigns') }}" method="get">
    <label>Caută în UTM, adresă sau domeniu <input type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Ex. outreach sau google.com"></label>
    <label>Tip de trafic <select name="kind">
        <option value="all" @selected($kind === 'all')>Tot traficul</option>
        <option value="tagged" @selected($kind === 'tagged')>Cu UTM</option>
        <option value="untagged" @selected($kind === 'untagged')>Fără UTM</option>
    </select></label>
    <label>Sortează după <select name="sort">
        <option value="recent" @selected($sort === 'recent')>Ultima vizită</option>
        <option value="visits" @selected($sort === 'visits')>Cele mai multe vizite</option>
        <option value="conversions" @selected($sort === 'conversions')>Cele mai multe vizite convertite</option>
    </select></label>
    <button class="button" type="submit">Aplică</button>
    @if($search !== '' || $sort !== 'recent' || $kind !== 'all')<a class="button ghost" href="{{ route('admin.campaigns') }}">Resetează</a>@endif
</form>
<dl class="campaign-metrics" aria-label="Totaluri pentru linkurile filtrate">
    <div><dt>Linkuri</dt><dd>{{ $number($totals->links) }}</dd></div>
    <div><dt>Vizite</dt><dd>{{ $number($totals->visits) }}</dd></div>
    <div><dt>Contacte</dt><dd>{{ $number($totals->contacts) }}</dd></div>
    <div><dt>Conturi create</dt><dd>{{ $number($totals->registrations) }}</dd></div>
    <div class="campaign-rate"><dt>Rată de conversie</dt><dd>{{ $rate($totals->converted_visits, $totals->visits) }}</dd></div>
</dl>
<p class="fine campaign-explanation">Conversia este o vizită cu cel puțin un formular de contact trimis cu succes sau un cont creat. Dacă apar ambele, vizita intră o singură dată în rată. Atribuirea folosește ultimul link UTM activ sau, în lipsa lui, ultima intrare fără UTM din sesiune. Fereastra este de două ore.</p>

<section class="panel campaign-country-panel" aria-labelledby="campaign-countries">
    <h2 id="campaign-countries">Vizite și conversii pe țări</h2>
    <p class="fine">Țara este estimată la intrarea pe site. Conversiile se atribuie acelei vizite.</p>
    @if($countries->isNotEmpty())
        <div class="campaign-country-scroll" role="region" aria-labelledby="campaign-countries" tabindex="0">
            <table class="campaign-country-table">
                <thead>
                    <tr><th rowspan="2" scope="col">Țară</th><th colspan="2" scope="colgroup">Cu UTM</th><th colspan="2" scope="colgroup">Fără UTM</th></tr>
                    <tr><th scope="col">Vizite</th><th scope="col">Conversii</th><th scope="col">Vizite</th><th scope="col">Conversii</th></tr>
                </thead>
                <tbody>
                    @foreach($countries as $country)
                        <tr data-country="{{ $country->country_code }}">
                            <th scope="row">{{ $countryName($country->country_code) }}</th>
                            <td>{{ $number($country->tagged_visits) }}</td><td>{{ $number($country->tagged_conversions) }}</td>
                            <td>{{ $number($country->untagged_visits) }}</td><td>{{ $number($country->untagged_conversions) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="empty-state">Nu există încă vizite cu țară măsurată pentru filtrele selectate.</p>
    @endif
    @if($countryCoverage > 0)
        <p class="fine">{{ $number($countryCoverage) }} vizite anterioare măsurării pe țări apar doar în totalurile linkurilor.</p>
    @endif
    <p class="fine"><a href="https://db-ip.com">Date IP de la DB-IP</a></p>
</section>

<section aria-labelledby="campaign-links">
    <h2 id="campaign-links">Linkuri înregistrate</h2>
    @forelse($links as $link)
        <article class="panel campaign-entry">
            <div class="campaign-entry-heading">
                <h3>{{ $link->tags['utm_campaign'] ?? 'Trafic fără UTM' }}</h3>
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
            @if($link->countries->isNotEmpty())
                <details class="campaign-sources">
                    <summary>Țări pentru acest link</summary>
                    <div class="campaign-country-scroll">
                        <table class="campaign-country-table">
                            <thead><tr><th scope="col">Țară</th><th scope="col">Vizite</th><th scope="col">Conversii</th></tr></thead>
                            <tbody>
                                @foreach($link->countries as $country)
                                    <tr><th scope="row">{{ $countryName($country->country_code) }}</th><td>{{ $number($country->visits) }}</td><td>{{ $number($country->converted_visits) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
            <details class="campaign-sources">
                <summary>Proveniența vizitelor</summary>
                <dl>
                    @foreach($link->sources as $source)
                        <div><dt>{{ $source->host ?: 'Directă / necunoscută' }}</dt><dd>{{ $number($source->visits) }}</dd></div>
                    @endforeach
                    @if($link->other_sources)
                        <div><dt>Alte surse</dt><dd>{{ $number($link->other_sources) }}</dd></div>
                    @endif
                    @if($link->earlier_visits)
                        <div><dt>Vizite anterioare, fără măsurarea provenienței</dt><dd>{{ $number($link->earlier_visits) }}</dd></div>
                    @endif
                </dl>
            </details>
        </article>
    @empty
        <p class="empty-state">{{ $search !== '' || $kind !== 'all' ? 'Niciun link nu corespunde filtrelor.' : 'Linkurile apar aici după prima intrare pe pagina publică.' }}</p>
    @endforelse
    @if($links->hasPages())
        <nav class="campaign-pagination" aria-label="Paginile campaniilor">
            @if($links->previousPageUrl())<a class="button ghost" href="{{ $links->previousPageUrl() }}">← Anterioară</a>@endif
            <span>Pagina {{ $links->currentPage() }} din {{ $links->lastPage() }}</span>
            @if($links->nextPageUrl())<a class="button ghost" href="{{ $links->nextPageUrl() }}">Următoarea →</a>@endif
        </nav>
    @endif
</section>
<p class="fine campaign-explanation">Vizitele sunt estimări. Sistemele de scanare a linkurilor pot genera vizite, iar conversiile de pe alt dispozitiv sau după expirarea sesiunii pot rămâne neatribuite. Proveniența afișează domeniul trimis de browser. Dacă lipsește, vizita apare ca directă / necunoscută. Căutarea după domeniu găsește linkurile asociate. Totalurile rămân ale linkurilor întregi.</p>
<p class="fine" data-campaign-copy-status role="status"></p>
@endsection
