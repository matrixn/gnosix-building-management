# Genesis 003 — Workspace Studio

Implementare peste Task 002, fără înlocuirea autentificării, a guardurilor, a profilelor sau a onboardingului pentru organizații. Laravel 13.42 / PHP 8.4, Blade, CSS și JavaScript progresiv cu Vite. Nu au fost adăugate pachete Composer/NPM: validarea Laravel, policies, tranzacțiile și discul privat existent acoperă acest task.

## Utilizare

Din organizația activă deschide **Workspace Studio**. Alege scenariul, numește proprietatea și ajustează numai câmpurile relevante. O casă/cabană are două etape; celelalte scenarii au trei. Mod ghidat poate fi oprit pentru a vedea întrebările disponibile împreună. Explicațiile „De ce întrebăm?” sunt elemente native details/summary, accesibile cu tastatura. Progresul și selecțiile au tranziții discrete; prefers-reduced-motion oprește tranzițiile.

Salvarea automată este serializată; există și salvare manuală. Ciorna și etapa sunt separate pentru fiecare utilizator și organizație. Înapoi/înainte în Studio și refresh păstrează răspunsurile. La schimbarea scenariului se păstrează valorile modificate; răspunsurile devenite ascunse rămân în ciornă și nu influențează structura creată. Confirmarea rulează atomic și este idempotentă. „Configurează alte proprietăți” deschide un lot nou fără a modifica structurile anterioare.

Scenarii implicite, ajustabile înainte de confirmare:

| Scenariu | Structură sugerată |
| --- | --- |
| Casă/cabană | 1 proprietate, 1 clădire, fără camere sau etaje inventate |
| Pensiune | 1 clădire, 1 etaj, 5 camere |
| Hostel | 1 clădire, 1 etaj, 2 dormitoare × 6 paturi; camere private opționale |
| Hotel | 1 clădire, 4 etaje, 40 camere |
| Resort | 3 clădiri, 1 etaj/clădire, 30 unități în total; facilități comune opționale |
| Grup hotelier | 3 proprietăți, portofoliu și entitate juridică opțională; structură ajustabilă per lot |

Camerele și dormitoarele se distribuie între clădiri/etaje; numerotarea este consecutivă, cu prefix opțional, separat pentru fiecare proprietate. După generare, fiecare înregistrare se poate redenumi și edita. Unitățile generate pot fi camere, apartamente sau case. Numărul de paturi este stocat per dormitor, fără inventar individual sau rezervări de paturi.

## Modelul datelor

`organizations` rămâne tenantul. `workspace_groups` reprezintă grupuri, branduri sau portofolii, cu relație many-to-many prin `property_group`. `legal_entities` permite asocierea opțională a unei entități la fiecare proprietate. Nici grupul, nici entitatea juridică nu înlocuiesc tenantul.

`properties → buildings → floors` și `properties → spaces` formează structura. Un spațiu are întotdeauna o proprietate, dar clădirea și etajul sunt opționale. Un etaj indicat trebuie să aparțină clădirii indicate, iar ambele trebuie să aparțină proprietății autorizate. Tipul spațiului este independent de profilul industriei. Suprafețele sunt normalizate în `area_m2`; preferința organizației controlează introducerea și afișarea în m² sau ft².

Organizația păstrează țara, fusul orar, limba implicită, unitățile, denumirea juridică și contactul opțional. Proprietățile noi moștenesc țara/fusul orar; pot fi ajustate separat. Limba organizației determină numele sugerate pentru noile clădiri/etaje/spații; limba interfeței rămâne preferința utilizatorului. Schimbarea preferințelor nu redenumește date existente și nu convertește retroactiv suprafețele normalizate.

`workspace_drafts` păstrează state/step/guided/version/result/completed_at per user + organizație. `user_preferences.guided_setup` este preferința pentru ciorne noi. Nu se reutilizează și nu se suprascrie `onboarding_sessions` din Task 002.

Activele și sistemele tehnice pot fi adăugate ulterior folosind proprietatea/spațiul drept context; acest release nu creează active sau documente fictive.

## Schema reutilizabilă

`config/workspace.php` definește scenarii, pași, tipuri, opțiuni, valori implicite, condiții AND cu apartenență/minim numeric și valori pentru câmpurile ascunse. `WorkspaceQuestions` validează inputul, filtrează cheile, evaluează vizibilitatea și calculează planul. Același JSON este consumat în `resources/js/studio.js`; nu există șase wizards separate.

`WorkspaceStudio` gestionează autorizarea, blocarea organizației/ciornei în tranzacție, versiunea optimistă și materializarea. Confirmarea verifică toate câmpurile relevante și limita de 5.000 înregistrări per lot. Un lot suportă până la 50 proprietăți, 30 clădiri/proprietate, 40 etaje/clădire, 500 unități/proprietate și 100 dormitoare/proprietate; acestea sunt limite de cerere, nu o restricție de tipul „o organizație = o clădire”. Se pot crea loturi suplimentare și proprietăți manuale.

Fără JavaScript sunt disponibile formularele și salvarea manuală; după schimbarea scenariului salvează și reîncarcă pentru întrebările condiționale. Preview-ul live și autosave necesită JavaScript.

## Autorizare

- Owner: administrarea organizației, structurii și membrilor; rolul nu poate fi retrogradat sau restrâns din aceste formulare.
- Admin: administrarea organizației și structurii când `access_scope=all`; nu poate schimba membri sau granturi. `manager` rămâne alias compatibil cu rolul administrativ anterior.
- Member cu acces all: consultarea structurii, fără modificări.
- Orice non-owner poate fi limitat la proprietăți desemnate prin `property_accesses`, viewer sau editor. Un admin restricționat nu mai poate administra întreaga organizație. Editorul poate modifica structura propriei proprietăți, dar nu apartenența la grupuri/entități juridice.

`OrganizationPolicy`, `PropertyPolicy` și `PropertyPermissions` controlează cererile și filtrează listele. Un grant nu oferă acces dacă utilizatorul nu mai este membru. Nici ID-urile parent, nici headerul tenantului și nici organizația activă nu înlocuiesc autorizarea resursei. Sunt verificate separat capabilitățile hospitality.workspace/buildings/rooms; opțiunile inactive sunt refuzate pe rutele directe.

Autentificarea, verificarea emailului, cookie-urile, no-store/noindex, CSRF și rate limiting existente sunt păstrate. Adminul platformei nu primește acces implicit la clienți. Nu sunt introduse variabile GNOSIX în mediu.

## Logo-uri și deploy

Logo raster PNG/JPEG/WebP, maximum 2 MB și 2048 × 2048 px, verificat server-side; SVG este refuzat. Fișierele se stochează pe discul privat local și se livrează prin ruta autorizată organizației. Fișierul vechi se șterge după salvarea reușită a înlocuirii; un upload nou se curăță dacă tranzacția eșuează. `storage` trebuie partajat și inclus în backup, împreună cu DB. Nu este necesar `storage:link`.

Migrarea `2026_11_16_120000_create_adaptive_workspace` este aditivă; rolurile și apartenențele existente se păstrează cu acces all. Forge rulează migrarea înainte de activarea release-ului. `gnosix:check` include structura Studio. Nu rula rollback pe date reale; down ar șterge tabelele adăugate. Arhiva 0.8.0 include codul, documentația și frontend-ul compilat, fără runtime sau secrete.

## Verificare și fișiere

- `php vendor/bin/phpunit`: 115 teste / 958 aserțiuni, inclusiv cele șase scenarii și regresiile Task 001/002, SMTP și WebAuthn.
- `npm run build`; `composer validate --no-check-publish`; lint PHP (106 fișiere, zero erori) și cache rute/view-uri. `gnosix:check` și rollback/remigrare a migrării noi au trecut pe baza izolată de preview.
- Browser local: login, Studio RO/EN, autosave, modificare camere, back/forward și refresh, confirmare, listare proprietăți/clădiri/etaje/spații, editare și creare manuală spațiu comun, setări, viewport 1440/390 px fără overflow. Frontend-ul final verificat fără erori de consolă.
- Teste dedicate: stale drafts, idempotency, limite și atomicitate, state whitelisting, capabilități dezactivate, roluri/granturi/revocare, ID-uri străine, parent mismatch la update parțial, păstrarea suprafeței, conversie ft², logo privat/SVG și CSRF.

Fișierele principale sunt în `app/Support/Workspace*`, `PropertyPermissions`, cele trei controllere noi Identity, noile modele/policy, `config/workspace.php`, `routes/workspace.php`, `resources/views/studio`, `resources/views/properties`, `resources/js/studio.js`, `resources/css/studio.css`, `lang/{ro,en}/studio.php` și `tests/Feature/WorkspaceStudioTest.php`. Au fost adaptate registrul de capabilități, layoutul, dashboardul, politicile existente, verificarea instalării, informarea de confidențialitate și testul de conservare a clădirilor din Task 002.

## Limite rămase

Nu sunt implementate arhivare/ștergere cu recuperare, mutare între proprietăți, invitații email, transfer owner, sisteme/active tehnice, mentenanță, documente/dovezi, inventar/rezervări individuale de paturi sau importuri. Formularul de granturi aplică același nivel viewer/editor tuturor proprietăților selectate pentru membru; motorul poate stoca niveluri individuale. Construction/Facility rămân coming soon. Nu s-a efectuat deploy pe VPS și nu s-au trimis emailuri externe. Suitele DB au rulat pe SQLite; verificarea pe motorul DB și infrastructura VPS rămâne pasul de deploy.
