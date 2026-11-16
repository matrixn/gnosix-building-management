# Building Studio unificat — 0.11.0

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

## Ștergerea clădirii

În **Camere și spații**, butonul **Șterge clădirea** de sub card este disponibil pentru editorii proprietății. Deschide un dialog modal în aceeași pagină, cu numele/referința clădirii și efectul operațiunii. **Anulează** sau Escape închide dialogul fără modificări; focusul revine la butonul inițial. Pagina/ruta separată de confirmare au fost eliminate. **Da, șterge clădirea** trimite cererea DELETE cu CSRF, confirmare și versiunea afișată.

Clădirea primește `deleted_at` și dispare din liste, filtre, Studio, dashboard și exporturi. Nivelurile, spațiile, identitățile de paturi și unitățile sunt arhivate în aceeași tranzacție; UUID-urile, legăturile și reviziile nu sunt șterse fizic. Se înregistrează autorul, momentul și structura anterioară în revizia `deleted`. Alte clădiri/proprietăți nu sunt afectate. Restaurarea este disponibilă din **Coș de gunoi** și folosește istoricul ștergerii, nu doar eliminarea lui deleted_at.

Serverul verifică accesul la proprietate și capabilitățile. Dacă o altă modificare a intervenit după afișarea confirmării, ștergerea este refuzată până la reîncărcarea listei și redeschiderea dialogului. Ciornele vechi nu pot fi deschise sau confirmate după ștergere. Referințele clădirilor șterse rămân rezervate și produc eroare de validare dacă sunt reutilizate.

Migrarea `2026_11_16_180000_add_building_soft_deletion` adaugă un timestamp nullable. Forge o aplică prin `php artisan migrate --force` în deploy-ul existent. Nu rula rollback pe date reale: eliminarea coloanei ar pierde marcajele ștergerilor.

## Coș de gunoi și restaurare

**Camere și spații → Coș de gunoi → Restaurează clădirea**. Lista afișează 24 de clădiri pe pagină, proprietatea, referința și data ștergerii. Apartenența, capabilitățile și granturile pe proprietate se aplică și aici; viewerii pot vedea clădirile autorizate, iar editorii le pot restaura.

Restaurarea se execută într-o tranzacție, sub blocarea proprietății și a clădirii. Folosește UUID-urile din structura `before_data` a ultimei revizii `deleted`, inclusiv pentru ștergerile înregistrate de versiunea 0.10.1. Se reactivează doar nivelurile/spațiile/paturile/unitățile active la momentul ștergerii; cele arhivate anterior rămân arhivate. Valorile, pozițiile nivelurilor, ID-urile, UUID-urile și legăturile existente nu se rescriu. Starea anterioară draft/active/archived este restaurată.

O revizie `restored` păstrează autorul, momentul și structura rezultată. Versiunea crește, astfel încât ciornele vechi nu pot suprascrie restaurarea. Repetarea ștergerii/restaurării nu creează copii ale structurii. Istoricul afișează linkuri de export numai pentru snapshot-uri complete.

Dacă istoricul necesar lipsește, sunt rânduri lipsă sau versiunea este depășită, restaurarea este refuzată cu un mesaj explicit și nicio modificare parțială. Nu există ștergere definitivă sau golire a coșului în interfață.

## Validare și deploy

- `php vendor/bin/phpunit`: 158 teste / 1.495 aserțiuni; include biblioteca, granturi viewer/editor, filtre/tenant, linkuri vechi, asociere, 5 × 10 camere, modificare și mutare fără pierderea datelor/identității.
- `node tests/building-generator.mjs`: 10 teste; exemplul hotelului, unknown/zero, append/replace, integritatea legăturilor bed/unit, subsol/parter, numerotare și limite cumulative.
- `npm run build`: build production; modulul Studio este încărcat dinamic numai pe paginile sale.
- Browser 0.11.0: dialog fără schimbarea URL-ului, Anulează/Escape, mutare în coș, restaurarea unei clădiri șterse și redeschidere în Studio cu 2 niveluri/4 camere/8 paturi declarate, RO/EN și coș mobil 390 × 844 fără overflow sau erori de consolă.
- Browser local cu cont/bază de test separate: creare 50 camere, modificare/mutare/salvare, redeschidere din dashboard și bibliotecă, extindere/undo/autosave, asociere spațiu vechi, RO/EN, tastatură, viewport 390 × 844 fără overflow orizontal și fără erori/warnings de consolă.

Versiunea 0.11.0 reutilizează migrarea deleted_at din 0.10.1; nu adaugă migrații, setări sau dependențe noi. Forge folosește fluxul existent: Composer, build Vite, migrații, cache și verificări. Arhiva `build/gnosix-atlas-forge-0.11.0.zip` conține sursele și frontend-ul compilat, fără `.env`, DB sau datele de test. Verificările sunt locale; deploy-ul pe VPS se execută separat.
