<section class="wrap intelligence" id="intelligence" aria-labelledby="intelligence-title">
    <div class="intelligence-heading">
        <div><span class="eyebrow">{{ __('DINCOLO DE O ARHIVĂ') }}</span><h2 id="intelligence-title">{{ __('O clădire. Mai multe conexiuni.') }}<br><em>{{ __('Explorează ce construim.') }}</em></h2></div>
        <p>{{ __('Ce știm, de unde știm și ce poate fi afectat? Descoperă direcția GNOSIX printr-un hotel fictiv cu 40 de camere.') }}</p>
    </div>
    <div class="intelligence-workspace">
        <div class="workspace-bar"><span><i></i> HOTEL DEMO / GNX–0040</span><span class="concept-label">{{ __('Concept interactiv · date fictive') }}</span></div>
        <div class="demo-navigation" data-demo-tabs data-demo-controls aria-label="{{ __('Explorează funcționalitățile') }}">
            <button type="button" id="time-tab" data-demo-target="time-demo"><span>01</span> Time Machine <b aria-hidden="true">↗</b></button>
            <button type="button" id="evidence-tab" data-demo-target="evidence-demo"><span>02</span> Evidence Engine <b aria-hidden="true">↗</b></button>
            <button type="button" id="dna-tab" data-demo-target="dna-demo"><span>03</span> Building DNA <b aria-hidden="true">↗</b></button>
        </div>
        <div class="demo-body">
            <article id="time-demo" data-demo-panel>
                <div class="demo-intro"><div><span class="eyebrow">TIME MACHINE</span><h3>{{ __('Schimbă anul. Păstrează contextul.') }}</h3></div><p>{{ __('Reconstituirea stării documentate a clădirii, la momentul ales. Informațiile lipsă rămân vizibile.') }}</p></div>
                <div class="year-controls" data-demo-tabs data-demo-controls aria-label="{{ __('Anul din istoricul clădirii') }}">
                    @foreach (['2022', '2024', '2026'] as $year)<button type="button" id="year-tab-{{ $year }}" data-demo-target="year-{{ $year }}">{{ $year }}<span aria-hidden="true">●</span></button>@endforeach
                </div>
                @foreach ([
                    ['2022', 'Predarea inițială', 'Centrală CT-01 instalată', 'Proces-verbal de punere în funcțiune', 'Garanție documentată până în 2025', 'Certificatul echipamentului este în dosar.', 'Acoperiș · informație lipsă', 'Nu există încă un raport de inspecție.'],
                    ['2024', 'Clădirea evoluează', 'Pompa P-02 înlocuită', 'Fișă de intervenție · iunie 2024', 'Garanție documentată până în 2025', 'Certificatul echipamentului este în dosar.', 'Acoperiș · inspecție înregistrată', 'Raportul din 2024 descrie o reparație locală.'],
                    ['2026', 'Un nou context de investigat', '4 incidente de apă caldă', 'Registrul intervențiilor · ultimele 90 de zile', 'Garanție expirată în 2025', 'O prelungire nu este documentată.', 'Acoperiș · ultima dovadă din 2024', 'Starea actuală necesită o nouă verificare.'],
                ] as [$year, $event, $equipment, $source, $warranty, $warrantyNote, $roof, $roofNote])
                <section id="year-{{ $year }}" class="year-snapshot" data-demo-panel aria-labelledby="snapshot-title-{{ $year }}">
                    <div class="snapshot-heading"><strong>{{ $year }}</strong><h4 id="snapshot-title-{{ $year }}">{{ __($event) }}</h4><span>{{ __('Stare documentată') }}</span></div>
                    <div class="snapshot-grid"><div><span>01 / {{ __('Instalație termică') }}</span><h4>{{ __($equipment) }}</h4><p>{{ __($source) }}</p></div><div><span>02 / {{ __('Garanții') }}</span><h4>{{ __($warranty) }}</h4><p>{{ __($warrantyNote) }}</p></div><div><span>03 / {{ __('Anvelopa clădirii') }}</span><h4>{{ __($roof) }}</h4><p>{{ __($roofNote) }}</p></div></div>
                </section>
                @endforeach
            </article>
            <article id="evidence-demo" data-demo-panel>
                <div class="demo-intro"><div><span class="eyebrow">EVIDENCE ENGINE</span><h3>{{ __('Trei surse. O întrebare deschisă.') }}</h3></div><p>{{ __('Alege un document și compară informațiile. O contradicție cere verificare, nu un verdict automat.') }}</p></div>
                <div class="evidence-layout">
                    <div class="source-list" data-demo-tabs data-demo-controls aria-label="{{ __('Documente sursă') }}">
                        <button type="button" id="source-plan-tab" data-demo-target="source-plan"><span>01 / PDF</span>{{ __('Proiect tehnic') }}<b>↗</b></button>
                        <button type="button" id="source-invoice-tab" data-demo-target="source-invoice"><span>02 / PDF</span>{{ __('Factură echipament') }}<b>↗</b></button>
                        <button type="button" id="source-record-tab" data-demo-target="source-record"><span>03 / PDF</span>{{ __('Proces-verbal') }}<b>↗</b></button>
                    </div>
                    <div class="source-previews">
                        @foreach ([['plan', 'Proiect tehnic', 'Echipament prevăzut: CT-01 / model X.', 'An indicat: 2021. Seria nu este specificată.'], ['invoice', 'Factură echipament', 'Echipament achiziționat: model X / seria AX-204.', 'Data achiziției: 2022. Nu confirmă data instalării.'], ['record', 'Proces-verbal', 'Echipament recepționat: model X / seria AX-240.', 'Seria diferă de cea din factură. Necesită verificare.']] as [$id, $name, $line, $note])
                        <section id="source-{{ $id }}" class="document-preview" data-demo-panel><span class="document-stamp">{{ __('EXTRAS DEMONSTRATIV') }}</span><h4>{{ __($name) }}</h4><p>{{ __($line) }}</p><p class="document-highlight">{{ __($note) }}</p><span class="fine">{{ __('Sursă fictivă pentru ilustrarea conceptului') }}</span></section>
                        @endforeach
                    </div>
                </div>
                <div class="evidence-insight"><span aria-hidden="true">!</span><div><h4>{{ __('De verificat: seria echipamentului') }}</h4><p>{{ __('AX-204 și AX-240 apar în documente diferite. Compararea cu plăcuța echipamentului poate clarifica înregistrarea. Nicio sursă nu este declarată automat falsă.') }}</p></div></div>
            </article>
            <article id="dna-demo" data-demo-panel>
                <div class="demo-intro"><div><span class="eyebrow">BUILDING DNA</span><h3>{{ __('Atinge o instalație. Vezi legăturile.') }}</h3></div><p>{{ __('Relațiile înregistrate conectează echipamentele cu spațiile deservite. Explorează dependențele din acest exemplu.') }}</p></div>
                <div class="dna-map">
                    <div class="dna-origin"><span aria-hidden="true">⌘</span><strong>HOTEL DEMO</strong><small>{{ __('40 de camere · 2 circuite') }}</small></div>
                    <div class="dna-systems" data-demo-controls role="group" aria-label="{{ __('Selectează o instalație') }}">
                        <button type="button" data-dna="boiler" aria-pressed="true" data-summary="{{ __('CT-01 deservește ambele circuite: camerele 101–120 și 201–220. Toate cele 40 de camere depind de această centrală în exemplu.') }}"><small>CT-01</small>{{ __('Centrală termică') }}<span>↘ ↙</span></button>
                        <button type="button" data-dna="pump" aria-pressed="false" data-summary="{{ __('Pompa P-02 este legată de circuitul B: camerele 201–220. Cele 20 de camere de pe circuitul A nu sunt dependente de această pompă în exemplu.') }}"><small>P-02 / B</small>{{ __('Pompă circuit B') }}<span>↘</span></button>
                        <button type="button" data-dna="circuit" aria-pressed="false" data-summary="{{ __('Circuitul A deservește camerele 101–120. Relațiile indică 20 de camere dependente; nu confirmă existența unei defecțiuni.') }}"><small>CIRCUIT A</small>{{ __('Distribuție etaj 1') }}<span>↙</span></button>
                    </div>
                    <div class="dna-rooms"><div data-dna-room="boiler circuit"><span>01 / CIRCUIT A</span><strong>{{ __('Camerele 101–120') }}</strong><small>{{ __('20 de camere') }}</small></div><div data-dna-room="boiler pump"><span>02 / CIRCUIT B</span><strong>{{ __('Camerele 201–220') }}</strong><small>{{ __('20 de camere') }}</small></div></div>
                </div>
                <p class="dna-result" data-dna-result role="status">{{ __('CT-01 deservește ambele circuite: camerele 101–120 și 201–220. Toate cele 40 de camere depind de această centrală în exemplu.') }}</p>
                <p class="demo-footnote">{{ __('Dependențele oferă context pentru investigație. Cauza unei defecțiuni trebuie confirmată de un specialist.') }}</p>
            </article>
        </div>
        <div class="workspace-footer"><span class="pulse-dot"></span><p>{{ __('Funcționalități planificate. Această demonstrație folosește scenarii prestabilite; nu analizează documente reale și nu efectuează un diagnostic.') }}</p></div>
    </div>
    <div class="future-heading"><span class="eyebrow">{{ __('ACELEEAȘI DATE. VALOARE PE TERMEN LUNG.') }}</span><h3>{{ __('Memoria rămâne. Chiar când echipa se schimbă.') }}</h3></div>
    <div class="future-grid">
        <article><span class="future-icon" aria-hidden="true">↗</span><span class="eyebrow">ZERO-ENTRY</span><h4>{{ __('De la documente la context.') }}</h4><p>{{ __('Importul asistat va propune echipamente, garanții și relații din arhiva existentă. Echipa verifică înainte de confirmare.') }}</p></article>
        <article><span class="future-icon" aria-hidden="true">◇</span><span class="eyebrow">TRUST PASSPORT</span><h4>{{ __('Afirmații cu proveniență.') }}</h4><p>{{ __('Introdus manual, documentat sau confirmat: nivelul de verificare va arăta cine susține informația și cu ce dovezi.') }}</p></article>
        <article><span class="future-icon" aria-hidden="true">⌁</span><span class="eyebrow">LIFECYCLE SIMULATOR</span><h4>{{ __('Planuri, cu ipotezele la vedere.') }}</h4><p>{{ __('Scenarii de înlocuire și buget bazate pe istoricul disponibil. Estimări explicabile, cu ipoteze vizibile.') }}</p></article>
        <article class="handover-card"><span class="eyebrow">HANDOVER INTELLIGENCE</span><h4>{{ __('Ce predai mai departe?') }}</h4><p>{{ __('Explorează un dosar de predare demonstrativ.') }}</p>
            <div class="handover-checks" data-demo-controls>
                @foreach (['Documente și garanții', 'Intervenții deschise', 'Informații de verificat'] as $item)<label><input type="checkbox" data-handover> {{ __($item) }}</label>@endforeach
                <div class="handover-track" aria-hidden="true"><span data-handover-bar></span></div>
                <p data-handover-result role="status" data-progress="{{ __(':count din 3 categorii revizuite') }}" data-complete="{{ __('3 din 3 categorii revizuite. Următorul pas propus: confirmarea recepției de către noua echipă.') }}">{{ __('0 din 3 categorii revizuite') }}</p>
            </div>
            <p class="demo-footnote">{{ __('Istoricul ar urma clădirea, cu acces controlat între echipe.') }}</p>
        </article>
    </div>
    <a class="text-link intelligence-cta" href="#contact">{{ __('Vreau să contribui la pilot') }} <span aria-hidden="true">↗</span></a>
</section>
