# Genesis 004 — Smart Building Declaration Studio

Baseline: `862cf6d`. Laravel 13.42 / PHP 8.4, Blade/Vite, identitate client și administrare platformă separate. Se extind modelele Task 003; nu se înlocuiește autentificarea și nu se adaugă dependențe Composer/NPM sau servicii externe.

## Metoda vizuală aleasă

Machetă axonometrică SVG nativă cu niveluri separabile și plan schematic al nivelului selectat, conectate la același model de date. Selectarea unui nivel actualizează spațiile și inspectorul; selectarea unui spațiu activează contextul corect. Culorile diferențiază cazarea, spațiile tehnice și zonele comune. Macheta se asamblează vizual, selecțiile au feedback, iar prefers-reduced-motion oprește animațiile.

Au fost evaluate [SVG.js](https://svgjs.dev/) și [Konva](https://konvajs.org/docs/performance/All_Performance_Tips.html). Pentru această interacțiune s-a ales SVG nativ: nu sunt necesare WebGL, un motor 3D sau o bibliotecă suplimentară. Elementele au alternative HTML și control cu tastatura; [SVG tabindex](https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/tabindex) susține focalizarea elementelor interactive.

Reprezentarea este explicit schematică. Celulele nu reprezintă suprafețe, pereți sau coordonate măsurate. Ordinea nivelurilor este ordine de prezentare, fără cote de înălțime deduse. Pentru mai mult de 12 niveluri se afișează o fereastră mobilă de 12, cu intervalul vizibil și totalul. Toate nivelurile rămân în selector și în DB. Lista afișează 40 spații per pagină; unitățile, 30. Pe mobil se pornește în plan schematic și navigarea aplicației este compactă. Inspectorul oferă acces la nume/numere chiar când desenul este mic.

## Fluxul utilizatorului

Din proprietate, deschide **Building Studio**. Alege un preset sau păstrează clasificarea necunoscută. Sunt disponibile casă, cabană, pensiune, hostel, hotel, resort, bloc de apartamente, clădire mixtă și tip personalizat. Tipurile sunt preseturi/configurare, nu tabele diferite sau restricții de industrie.

Completează numele și, dacă le cunoști, detaliile opționale. Referința este generată dacă rămâne goală la creare. Poți propune o structură numai prin acțiune explicită, apoi modifica fiecare nivel separat. Schimbarea presetului nu înlocuiește automat structura. Înlocuirea propunerii și importul cer confirmare locală; undo permite revenirea înaintea declarației.

Nivelurile se pot adăuga, copia, reordona și elimina. Tipurile includ parter, etaj, subsol, mezanin, mansardă/pod și nivel personalizat. Spațiile pot fi camere, dormitoare, apartamente, bucătării, băi, spații tehnice/comune, coridoare, depozitare și alte tipuri. Numele și numerele se editează individual; un nivel poate fi renumerotat fără a-i înlocui numele personalizate. Pot exista spații ale clădirii fără nivel declarat; spațiile direct în proprietate din Task 003 nu sunt mutate implicit în clădire.

**Salvează ciorna** persistă lucrul intermediar, fără a modifica structura live. Etapa este reluată după refresh. Unitatea de cazare poate fi încă incompletă în ciornă, dar declarația finală cere legăturile corecte. **Salvează declarația** creează sau actualizează identitatea și structura într-o singură tranzacție. Stările clădirii sunt draft/active/archived. Dashboardul dedicat păstrează vizualizarea, identitatea, unitățile și istoricul.

Nu se completează automat anul, adresa, coordonatele, țara sau fusul orar din presupuneri. Valorile lipsă rămân NULL. Anul acceptă 1–anul curent; coordonatele sunt o pereche opțională, în intervalele geografice valide. Țara și fusul orar folosesc listele existente de regiuni și identificatori IANA. Datele manuale/importate rămân declarate și neverificate documentar.

## Spații fizice, paturi și unități

`spaces` reprezintă spațiul fizic. `space_beds` reprezintă paturi individuale opționale, fiecare cu UUID și etichetă. Un număr de paturi cunoscut poate exista fără inventar individual; etichetele individuale nu sunt inventate prin migrare. Când există paturi individuale, numărul declarat trebuie să corespundă inventarului la confirmare.

`accommodation_units` este separat, cu relație many-to-many la spații prin `accommodation_unit_space`. O suită poate lega două sau mai multe spații; un dormitor poate avea unități individuale de pat. Unitatea de tip whole_building poate reprezenta o cabană întreagă fără camere fictive. Pentru o unitate de pat se verifică patul și spațiul său. O cameră fizică nu devine automat unitate de cazare: generarea lor se bifează explicit.

Acestea sunt unități declarate; nu există încă rezervări, disponibilitate, tarife sau reguli PMS pentru suprapuneri între unități. O organizație are mai multe proprietăți, iar o proprietate poate avea oricâte clădiri. Resortul cu trei clădiri este susținut prin declarații distincte sau structura Workspace Studio, fără a uni identitățile.

## Persistență și recuperare

Migrarea `2026_11_16_160000_add_building_declarations` adaugă UUID-uri la buildings/floors/spaces, referințe și metadate la clădiri, kind/arhivare la niveluri și arhivare la spații. Înregistrările existente primesc UUID-uri fără modificarea numelor, a pozițiilor sau inventarea clasificării/anului/țării. UUID-ul clădirii și asocierea sa cu proprietatea sunt imutabile prin model; UUID-urile spațiilor/nivelurilor rămân stabile.

`building_declaration_drafts` este separat de ciornele Task 002/003: user/property/building, state, stage, version și base_version. Salvarea verifică versiunea ciornei și versiunea clădirii. Dacă o modificare concurentă sau un formular clasic a schimbat clădirea, versiunea veche este refuzată. Utilizatorul poate descărca lucrul local și deschide structura curentă, fără suprascriere tăcută. Confirmarea repetată este idempotentă.

Eliminarea unui nivel/spațiu/pat/unități din declarație arhivează înregistrările, fără hard delete. Elementele salvate sunt actualizate după UUID, păstrând ID-urile și created_at. `building_revisions` păstrează autorul real al cererii, momentul, sursa, entitatea și datele înainte/după. `building_assertions` păstrează afirmațiile despre câmpurile identității, source_kind, source_reference și verification_state. Sunt append-only prin modelele aplicației. Nu există în acest release un API pentru a promova date din documente sau pentru a marca datele drept verificate.

Sursele disponibile editorului sunt manual și import; migrarea marchează datele existente legacy. Infrastructura pentru afirmații documentare viitoare este separată de valorile declarate. Nicio valoare extrasă nu suprascrie implicit datele manuale.

Formularele Task 003 rămân disponibile și mutațiile lor sunt auditate prin observer. Spațiile cu legături la unități/paturi nu pot fi mutate arbitrar între clădiri prin formularul clasic. Clădirile arhivate se reactivează din Studio. Crearea inițială în Workspace Studio înregistrează un snapshot per clădire, evitând mii de revizii inițiale per cameră.

Din istoric se pot descărca starea de după o declarație și, când există, starea anterioară. Importul lor în aceeași clădire poate recupera elementele cu aceleași identități. Recuperarea este o nouă revizie, nu ștergerea trecutului. Evenimentele clasice păstrează snapshot-uri ale entității; exportul recuperabil complet este disponibil pentru reviziile de declarație.

## Import/export și limite

Exportul folosește `format: "gnosix.building.v1"`, `building_uuid` și `structure`. Structura conține identity, levels, unplaced_spaces și units. Nivelurile/spațiile/paturile/unitățile folosesc key UUID. Spațiile conțin name, space_type, number, bed_count, capacity, area_m2 și beds; unitățile conțin name, unit_type, space_keys și bed_key. Exportul curent poate fi folosit drept exemplu de format.

Fișierul JSON se citește în browser și structura se validează din nou pe server înainte de randarea importului. Fișierul original nu este stocat pe server. Importul normal păstrează identitățile și acceptă doar identități ale aceleiași clădiri sau identități noi. Pentru altă clădire, bifează **Folosește ca șablon, cu identități noi**: se remapează nivelurile/spațiile/paturile/unitățile și legăturile, iar referința se generează din nou.

Limite per declarație: 2 MB, 100 niveluri, 5.000 spații, 10.000 paturi și 5.000 unități. Upsert-urile se execută în loturi de 250, legăturile în loturi de 500. Snapshot-ul grupează spațiile pe nivel o singură dată. Datele agregate nu înlocuiesc necunoscutul cu zero: paturile complet necunoscute sunt „—”, iar un total parțial cunoscut este marcat ≥.

Backfill-ul identităților și reviziilor inițiale este executat în loturi de 250. Rollback/remigrare pe fixture-ul cu peste 5.000 spații a trecut; migrarea optimizată a durat aproximativ 2 secunde local. Testul de upgrade verifică și păstrarea suprafețelor/numerelor de înregistrări pe mai multe loturi. Nu folosi rollback pe date reale.

Măsurare locală, PHP 8.4/SQLite: salvare ciornă + confirmare pentru 100 niveluri/5.000 spații aproximativ 6,6 secunde; o confirmare separată în fixture-ul de browser aproximativ 3,8 secunde. Acestea nu sunt benchmark-uri VPS sau teste de încărcare multi-utilizator. Browserul a navigat până la nivelul 100, cu 12 niveluri desenate, 40 carduri și 471 noduri SVG în acea scenă.

## Autorizare și verificări

Toate rutele sunt în grupul client verificat, cu sesiunea, CSRF și rate limiting existente. PropertyPolicy/PropertyPermissions verifică organizația, apartenența și granturile viewer/editor. UUID-urile de clădiri/revizii/ciorne și fiecare cheie importată sunt verificate contra proprietății, utilizatorului și clădirii țintă. Organizația activă sau un header nu înlocuiesc aceste verificări.

Capabilitățile hospitality.buildings și hospitality.rooms se aplică server-side. Când spațiile sunt dezactivate, dashboardul nu include spații/unități în JSON și exporturile care le-ar dezvălui sunt refuzate. Adminul platformei nu primește acces implicit la client. Rendererul construiește DOM/SVG cu textContent, fără interpolare HTML a numelor/importurilor.

- `php vendor/bin/phpunit`: 143 teste / 1.322 aserțiuni, inclusiv toate cele zece scenarii de acceptanță, migrare/backfill/rollback, IDOR, ciorne concurente, granturi, CSRF, necunoscut, istoric și regresii auth/SMTP/WebAuthn.
- `npm run build`, verificare sintaxă JS, PHP lint, Composer validate, cache rute/view-uri și `gnosix:check` pe baza izolată.
- Browser: creare/editare, selecție precisă între niveluri, undo, autosave/refresh, spațiu tehnic, suită cu două spații, confirmare și istoric, download real și import ca șablon pentru a doua clădire, RO/EN, 1600/390 px, motion redus și clădire de 5.000 spații. Frontend final fără erori de consolă.

## Fișiere și deploy

Principalele fișiere: `config/building-studio.php`, migrarea nouă, `app/Support/BuildingDeclarationSchema.php`, `BuildingDeclarationStudio.php`, `BuildingAudit.php`, observerul, modelele noi, `BuildingDeclarationController`, `routes/building-studio.php`, `resources/views/buildings`, `resources/js/building-{studio,scene}.js`, CSS și cataloagele RO/EN. Au fost adaptate modelele existente, PropertyController, WorkspaceStudio, layoutul, navigarea mobilă, informarea de confidențialitate, README, check-ul instalării și pachetul Forge.

Arhiva 0.9.1 include codul și frontend-ul compilat, fără vendor/.env/runtime. Scriptul Forge existent rulează migrarea înainte de activare și gnosix:check verifică structura declarațiilor. Nu este necesară o comandă nouă de inițializare. Fă backup înainte de migrare și nu rula down pe date reale: elimină noile identități, istoricul și unitățile/paturile.

Rămân pentru etapele viitoare: upload/extragere/review de documente, geometrie măsurată/IFC/BIM, rezervări PMS, mutarea clădirilor între proprietăți, transferul între organizații, politici automate de retenție și audit pentru administratori DB externi aplicației. Nu s-a efectuat deploy pe VPS. Suita automată rulează pe SQLite. Corecția 0.9.1 a fost verificată separat pe MySQL 8.4.13: reproducere 1059, reluarea migrației parțiale cu identități/date păstrate, constrângeri complete, salvare declarație și retry idempotent. Vezi recuperarea în DEPLOY-FORGE.md; infrastructura finală trebuie verificată la deploy.

Din 0.12.0, ștergerea definitivă individuală din coș este implementată cu confirmare și tranzacție. Vezi BUILDING-STUDIO-WORKFLOWS.md.
