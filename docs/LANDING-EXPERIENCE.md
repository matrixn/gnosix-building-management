# Experiența interactivă GNOSIX

Landing-ul Atlas folosește paleta administrării: bleumarin `#101225`, suprafețe `#191c35`, violet electric și lime `#b9f568` pentru acțiuni. Clădirea SVG, formularul, faviconul și imaginile sociale RO/EN folosesc aceeași direcție vizuală.

## Direcția de produs prezentată

Experiența de la `/#intelligence` și `/en#intelligence` transpune direcția din discuția „Găsire nișă SaaS inovatoare”: o identitate permanentă a clădirii, cu istoric, proveniență și relații între instalații. Nu include comparații sau afirmații de unicitate față de concurență.

- **Time Machine:** alegerea anilor 2022, 2024 și 2026 schimbă starea documentată a instalației, garanției și acoperișului. Lipsa sau vechimea dovezilor este explicită.
- **Evidence Engine:** trei documente fictive pot fi consultate separat. Exemplul evidențiază două serii diferite și necesitatea verificării lor, fără a declara un document fals.
- **Building DNA:** selectarea centralei, pompei B sau circuitului A evidențiază grupurile de camere dependente și actualizează explicația. Nu reprezintă un diagnostic.
- **Handover Intelligence:** un checklist demonstrativ actualizează numărul categoriilor revizuite; nu generează un certificat, un scor de încredere sau un transfer real.
- **Zero-Entry, Trust Passport și LifeCycle Simulator:** carduri care descriu funcții viitoare și rolul verificării umane.

Toate scenariile sunt prestabilite, locale în browser și marcate drept concepte cu date fictive. Modulele operaționale, analiza AI, importul de documente, simularea de buget și predarea reală între organizații nu sunt implementate prin această modificare. Nu se colectează sau trimit date prin demonstrație și nu există stocare a bifelor din checklist.

## Accesibilitate și traduceri

Conținutul este redat de Blade și tradus prin `lang/en.json`; româna este baza. Fără JavaScript, toate exemplele de ani și surse rămân lizibile, iar controalele care necesită JavaScript sunt ascunse. Taburile au legături ARIA, focus vizibil și navigare cu săgeți/Home/End. Harta și checklistul anunță modificările prin regiuni status. Preferința de reducere a animațiilor este respectată de stilurile existente.

Nu sunt introduse configurări în `.env`, dependințe frontend sau servicii externe. Setările publice și de contact rămân în administrare. Canonical, hreflang, sitemap și metadatele SEO existente sunt păstrate.

## Verificare

- Build Vite și compilare Blade reușite.
- PHPUnit: 57 teste, 378 aserțiuni; include conținutul demonstrativ RO/EN și regresiile contact/admin/SEO/passkeys.
- Browser: schimbarea anilor și surselor, dependențele pompei B, checklist complet și comanda Home pe taburi.
- Verificare la 390 px, lățimea implicită de aproximativ 773 px și 1440 px, fără overflow orizontal; verificare fără JavaScript și fără erori în consola browserului.
- Nu au fost trimise emailuri și nu a fost executat un deploy pe VPS în această etapă.
