<section class="panel campaign-chart" aria-labelledby="campaign-chart-title">
    <h2 id="campaign-chart-title">Evoluția vizitelor și conversiilor</h2>
    <p class="fine">{{ $period->start->format('d.m.Y') }} – {{ $period->end->format('d.m.Y') }} · Ora României @unless($period->limited()) · Ultimele 30 de zile în grafic @endunless</p>
    <p class="fine">Conversiile sunt numărate în ziua vizitei care le-a generat.</p>
    @if($coverageStart)<p class="fine">Primele date zilnice sunt din {{ $coverageStart }}.</p>@endif
    @if($covered)
        @php
            $observed = array_values(array_filter($series, fn ($row) => $row['date'] >= $firstDailyVisit));
            $maximum = max(1, ...array_map(fn ($row) => max($row['tagged_visits'], $row['untagged_visits']), $observed));
            $divisor = max(1, count($observed) - 1);
            $x = fn ($index) => count($observed) === 1 ? 286 : round(42 + $index * 490 / $divisor, 2);
            $y = fn ($value) => round(120 - 100 * ($value / $maximum), 2);
        @endphp
        <div class="campaign-charts">
            @foreach(['tagged'=>'Cu UTM', 'untagged'=>'Fără UTM'] as $key => $label)
                <figure>
                    <figcaption>{{ $label }}</figcaption>
                    <svg viewBox="0 0 550 152" aria-hidden="true">
                        @foreach(array_unique([0, (int) floor($maximum / 2), $maximum]) as $tick)
                            <line class="chart-grid" x1="42" y1="{{ $y($tick) }}" x2="532" y2="{{ $y($tick) }}"></line>
                            <text x="36" y="{{ $y($tick) + 4 }}" text-anchor="end">{{ $tick }}</text>
                        @endforeach
                        @foreach(['visits'=>'chart-visits', 'conversions'=>'chart-conversions'] as $seriesKey => $class)
                            @php($field = $key.'_'.$seriesKey)
                            @if(count($observed) === 1)
                                <circle class="{{ $class }}" cx="{{ $x(0) }}" cy="{{ $y($observed[0][$field]) }}" r="{{ $seriesKey === 'visits' ? 5 : 3 }}"></circle>
                            @else
                                <polyline class="{{ $class }}" points="{{ implode(' ', array_map(fn ($index, $row) => $x($index).','.$y($row[$field]), array_keys($observed), $observed)) }}"></polyline>
                            @endif
                        @endforeach
                        <text x="42" y="144">{{ $observed[0]['day'] }}</text>
                        @if(count($observed) > 1)<text x="532" y="144" text-anchor="end">{{ end($observed)['day'] }}</text>@endif
                    </svg>
                </figure>
            @endforeach
        </div>
        <ul class="campaign-chart-legend" aria-label="Legenda graficului"><li class="chart-visits">Vizite</li><li class="chart-conversions">Conversii</li></ul>
        <details class="campaign-sources">
            <summary>Vezi valorile pe zile</summary>
            <div class="campaign-country-scroll" role="region" aria-label="Valori zilnice" tabindex="0">
                <table class="campaign-country-table">
                    <thead>
                        <tr><th rowspan="2" scope="col">Zi</th><th colspan="2" scope="colgroup">Cu UTM</th><th colspan="2" scope="colgroup">Fără UTM</th></tr>
                        <tr><th scope="col">Vizite</th><th scope="col">Conversii</th><th scope="col">Vizite</th><th scope="col">Conversii</th></tr>
                    </thead>
                    <tbody>
                        @foreach($observed as $row)
                            <tr>
                                <th scope="row">{{ $row['day'] }}</th>
                                <td>{{ $number($row['tagged_visits']) }}</td><td>{{ $number($row['tagged_conversions']) }}</td>
                                <td>{{ $number($row['untagged_visits']) }}</td><td>{{ $number($row['untagged_conversions']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @else
        <p class="empty-state">Nu există date zilnice măsurate pentru această perioadă.</p>
    @endif
</section>
