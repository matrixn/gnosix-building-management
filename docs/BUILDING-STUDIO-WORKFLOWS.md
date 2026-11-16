# Building Studio unificat — 0.10.0

## Fluxul clientului

**Camere și spații → clădire → Building Studio → cameră → inspector → Salvează clădirea.**

Biblioteca afișează clădirile din organizația curentă la care utilizatorul are acces; schimbarea organizației rămâne în meniul existent. Are căutare după nume/referință, filtre proprietate/stare, 24 clădiri per pagină și numărul de niveluri/spații active. Utilizatorii cu drept de editare intră direct în Studio; viewerii văd dashboardul fără controale de scriere. Filtrele și camera deschisă prin link se păstrează la schimbarea limbii.

Pe pagina proprietății, clădirile se deschid prin aceleași carduri. Formularele separate de clădiri/niveluri/spații nu mai sunt afișate. Endpointurile vechi de scriere rămân pentru compatibilitate, cu politicile, validarea și auditul existente; nu sunt folosite de noua interfață. Linkul vechi `spaces/{space}/edit` redirecționează spre Studio și selectează camera.

## Generare rapidă: hotel cu 50 camere

1. Din bibliotecă, **Adaugă clădire**, apoi alege proprietatea.
2. Alege Hotel, completează numele și mergi la Structură.
3. Completează **5 niveluri**, primul nivel **1**, **10 spații per nivel**, **15 m²**, **2 paturi** și **2 locuri**.
4. Generează: nivelurile 1–5, camerele 101–110 … 501–510; total 50 spații, 750 m², 100 paturi și 100 locuri.
5. Click pe o cameră în plan/machetă/listă pentru ajustări individuale. **Salvează clădirea** confirmă structura și păstrează revizia.

Numărul de niveluri este totalul generat: primul nivel 0 înseamnă parter, 1 înseamnă etajul 1, −1 înseamnă subsol. Pentru parter plus cinci etaje completează șase niveluri începând cu 0. Peste 99 camere per nivel se folosesc coduri delimitate, pentru a evita coliziunile între nivelurile aceleiași generări.

Suprafața este întotdeauna în m² în Studio, conform etichetei. Paturile și capacitatea în persoane sunt independente: nu deducem locuri din paturi și nu inventăm valori necunoscute. Câmpurile goale rămân NULL, zero explicit rămâne zero. Identitățile de paturi individuale și unitățile de cazare sunt opțiuni separate. Un spațiu fizic nu devine automat rezervabil.

Generatorul verifică limitele cumulative înainte de alocare: 100 niveluri, 5.000 spații, 10.000 identități de paturi și 5.000 unități; serverul validează din nou structura și limita JSON de 2 MB. Paturile/capacitatea per cameră au maximum 1.000. Nu se adaugă biblioteci 3D sau alte dependențe.

## Editare și recuperare

La o clădire existentă, o ciornă nouă începe direct la Structură; o ciornă nefinalizată se reia la etapa salvată. Generatorul este restrâns pentru a prioritiza planul și inspectorul. Modul implicit **Adaugă la structura existentă** păstrează nivelurile/spațiile/unitățile și UUID-urile lor. **Înlocuiește structura din ciornă** cere confirmare; undo recuperează modificările locale înaintea confirmării. Structura eliminată se arhivează la salvare, iar reviziile rămân păstrate.

La click sau Enter pe cameră se editează numele, tipul, codul, suprafața, paturile și locurile. Selectorul de nivel mută camera fără schimbarea UUID-ului și păstrează legăturile unităților/paturilor. În inspectorul nivelului se pot adăuga mai multe camere cu aceleași valori inițiale. Pe mobil, selectarea unei camere aduce inspectorul în vedere. Planul afișează număr/suprafață/capacitate; desenul este schematic, fără geometrii arhitecturale măsurate.

**Salvează ciorna** persistă lucrul în curs. **Salvează clădirea** confirmă toate modificările clădirii într-o tranzacție, cu mecanismul existent de versiuni/concurență și audit; starea draft/active/archived se configurează în Declarație. În dashboard, click pe cameră redeschide Studio pentru editori; viewerii primesc doar detalii.

Spațiile vechi fără `building_id` apar separat în bibliotecă, 20 per pagină. Asociază explicit o clădire ne-arhivată din aceeași proprietate. Asocierea este tranzacțională, verifică accesul editor/capabilitatea rooms și păstrează UUID-ul și datele; apoi deschide inspectorul la **Fără nivel declarat**. Mutarea la etaj se face în Studio. Asocierea nu poate transfera spații între proprietăți sau reasocia pe ascuns un spațiu deja legat de o clădire.

## Validare și deploy

- `php vendor/bin/phpunit`: 149 teste / 1.390 aserțiuni; include biblioteca, granturi viewer/editor, filtre/tenant, linkuri vechi, asociere, 5 × 10 camere, modificare și mutare fără pierderea datelor/identității.
- `node tests/building-generator.mjs`: 10 teste; exemplul hotelului, unknown/zero, append/replace, integritatea legăturilor bed/unit, subsol/parter, numerotare și limite cumulative.
- `npm run build`: build production; modulul Studio este încărcat dinamic numai pe paginile sale.
- Browser local cu cont/bază de test separate: creare 50 camere, modificare/mutare/salvare, redeschidere din dashboard și bibliotecă, extindere/undo/autosave, asociere spațiu vechi, RO/EN, tastatură, viewport 390 × 844 fără overflow orizontal și fără erori/warnings de consolă.

Nu sunt necesare migrații sau setări noi. Forge folosește fluxul existent: Composer, build Vite, migrații, cache și verificări. Arhiva `build/gnosix-atlas-forge-0.10.0.zip` conține sursele și frontend-ul compilat, fără `.env`, DB sau datele de test. Verificările sunt locale; deploy-ul pe VPS se execută separat.
