# GNOSIX 0.15.0 — Smart Asset Registry

## Utilizare

Din **Registrul activelor**, alege Adaugă activ, apoi proprietatea, denumirea și categoria. Proprietatea ancorează drepturile de acces; clădirea, nivelul, spațiul și sistemul părinte sunt opționale. Alegerea unui spațiu poate deriva clădirea/nivelul deja cunoscute din relația spațială, fără a inventa informații. Câmpurile tehnice, achiziția, garanția și notele pot rămâne necompletate. Statusul inițial este „Necunoscut”, nu o afirmație că echipamentul funcționează. Referința AST și UUID-ul sunt generate o singură dată.

Preseturile HVAC, încălzire/pompe, electric, sanitar, ascensoare, securitate/incendiu, electrocasnice, mobilier și custom sunt categorii generice, nu tabele separate. În **Categorii și atribute**, owner/admin/manager cu acces organizațional poate crea categorii proprii și defini vizual câmpuri text, număr, întreg, Da/Nu sau dată și o unitate opțională. Schema JSON rămâne fallback fără JavaScript. Maximum 100 categorii personalizate pe organizație și 20 atribute per categorie. Preseturile software sunt păstrate pentru compatibilitate. Arhivarea unei categorii o retrage din selecțiile noi; activele existente și valorile vechi rămân. Retragerea unui câmp nu șterge valorile deja declarate.

Un activ poate reprezenta un **sistem tehnic comun**, poate avea componente și poate deservi explicit mai multe spații din clădiri diferite ale aceleiași proprietăți. Localizarea fizică și spațiile deservite sunt independente. Un sistem nu poate deveni propria componentă și nu poate fi transformat în obiect simplu cât timp are componente. Nu există condiții după tipul proprietății: același model susține casă/cabană, pensiune, hostel, hotel, resort și proprietățile unui lanț.

Pașaportul prezintă identitatea, localizarea, specificațiile, atributele, garanția, fotografiile, sursele, relațiile și cronologia. Datele secundare folosesc disclosure progresiv. Editarea folosește revizie optimistă; o pagină veche nu poate suprascrie o editare nouă. Arhivarea/restaurarea păstrează identitatea, datele, fotografiile și istoricul. Nu există ștergere definitivă a activelor în acest release.

## Building Studio

Click pe o cameră în Building Studio încarcă la cerere echipamentele localizate acolo și echipamentele comune care o deservesc. Sunt afișate opt rezultate pe pagină, cu acces la inventarul filtrat și Adaugă echipament aici pentru editori. Un spațiu nou trebuie salvat în Studio înainte să poată avea active asociate. Activele nu sunt serializate în snapshotul/ciorna structurii clădirii; editarea și importul structurii existente rămân independente.

## Dovezi și informații aprobate

Se reutilizează Evidence Engine: aceleași assertions, documents, versions, references, reviews și conflicts. Nu se creează un motor alternativ de extracție sau review documentar. Utilizatorul asociază explicit o afirmație sau o versiune deja existentă din aceeași proprietate. Documentele noi se încarcă prin Memoria clădirii, folosind fluxul existent privat și versionat.

Asocierea este doar o legătură. Nu confirmă afirmația și nu aplică informația pe activ. Pentru aplicare, afirmația trebuie confirmată în Evidence Studio, la aceeași revizie, fără conflicte deschise și cu sursa accesibilă. Apoi un reviewer cu assets.review, evidence.review și drept de editare pe proprietate aprobă folosirea ei pe pașaport, cu justificare. Sunt aplicabile cheile existente manufacturer/model/serial/install date/warranty end/in service. Restul afirmațiilor pot fi asociate ca surse, iar atributele personalizate rămân declarații manuale.

Declarația operatorului (`manual_data`) și aprobările documentare (`asset_fact_applications`) sunt separate. O valoare diferită de declarația manuală sau de o aprobare precedentă cere autorizare explicită de înlocuire. Aprobarea precedentă devine istoric; declarația manuală nu este rescrisă. Dacă sursa este înlocuită, revizuită, eliminată sau primește un conflict, pașaportul afișează necesitatea unui nou review. Un conflict rezolvat prin respingerea altei propuneri poate reda validitatea unei surse confirmate neschimbate. Retragerea unei asocieri retrage aprobările active, păstrând sursa și istoricul.

Încrederea AI, confirmarea umană, legătura documentară și verificarea profesională rămân lucruri diferite. Nu există certificare profesională independentă în acest release.

## Import asistat

Importul citește doar afirmațiile existente în documentele clădirilor din proprietățile pe care operatorul le poate edita. Grupează după clădire și identificator explicit de echipament, paginat 20 de grupuri, cu maximum 100 de afirmații recente în preview. Utilizatorul selectează afirmațiile și alege un activ existent sau declară un pașaport nou cu nume/categorie. Confirmarea este obligatorie; datele se asociază, nu se aplică automat.

Sugestiile folosesc seria normalizată sau identificatorul explicit în aceeași clădire. Seria compară textul păstrând zerourile inițiale, cu spații normalizate și majuscule. Producătorul și modelul comune nu reprezintă identitate fizică și nu declanșează matching automat. Serii duplicate sunt permise deoarece pot exista în realitate, dar declararea unui activ distinct cere confirmare când este găsită o potrivire. Lipsa unei serii nu produce potriviri generale între toate activele fără serie. Nu se fac merge-uri automate. Sugestiile sunt limitate la aceeași proprietate, la care operatorul are acces.

Nu există apeluri AI în import, fotografie, editarea sau salvarea activului. Extracția externă rămâne exclusiv în fluxul Evidence cu furnizor/configurare și consimțământ per versiune.

## Arhitectură și fișiere

- `database/migrations/2026_11_16_220000_create_smart_asset_registry.php`: migrare aditivă cu asset_types, assets, asset_served_spaces, asset_evidence_links, asset_fact_applications, asset_document_links, asset_photos și asset_audits. Indexuri pentru organizație/stare, localizare, serial, identificare, aprobări și istoric; nume de index scurte. UUID-urile sunt stabile, iar FK-urile rămân active.
- `app/Models/Asset*.php`: identitate, categorii, legături, aprobări, fotografii și audit. Relații assets adăugate în Organization/Property/Building/Floor/Space. Nu există câte un model/tabel pentru fiecare tip de echipament.
- `app/Assets/Registry.php`: validare tipizată, localizare, sisteme, duplicate, mutex Property → Asset, revizii, save/archive/restore/audit și integrarea cu purjarea clădirii.
- `app/Assets/EvidenceBridge.php`: asociere explicită cu arhitectura Evidence existentă, verificarea sursei/reviziei/conflictelor, aprobare/retragere și istoric fără suprascrierea declarației.
- `app/Assets/Photos.php`: validare imagini și stocare privată, cu rollback al fișierelor și reutilizarea outbox-ului de cleanup existent.
- `app/Policies/AssetPolicy.php`, AppServiceProvider, config/profiles.php, CapabilityService și PropertyPermissions: view/manage/review/types, dependența de assets.view și granturi viewer/editor existente. PropertyPermissions primește o opțiune de interogare a proprietăților editabile, cu comportamentul vechi de read păstrat implicit.
- `app/Http/Controllers/Identity/AssetController.php`, `routes/assets.php`: liste/filtre, pașaport, formulare, lookups limitate, import, documente, fotografii, room equipment și categorii, în grupul web verificat existent. Rutele folosesc organizația și UUID-ul activului; toate relațiile sunt verificate pe server.
- `resources/views/assets/*`, `resources/js/assets.js`, `resources/css/assets.css`: interfața RO/EN, editor vizual pentru atribute, lookup-uri pe server, photo lazy loading și layout responsiv. `asset-room.js` completează inspectorul Building Studio. Identity layout/JS/CSS includ modulele fără framework nou.
- `lang/{ro,en}/asset.php` și actualizări workspace/building: localizare reutilizabilă și înlăturarea mesajelor vechi că documentele/echipamentele urmează când ele sunt deja disponibile.
- `tests/Feature/AssetRegistryTest.php`: scenarii, permisiuni, dovezi, conflict, import, categorii, fotografii, retenție și dataset mare. Auditul inițial este în ASSET-REGISTRY-AUDIT.md.

Nu sunt dependențe Composer/NPM noi. Blade, Eloquent, validarea Laravel, controalele native, CSS și JS-ul existent sunt suficiente. Nu se migrează la Vue/Inertia și nu se modifică schema veche Evidence.

## Autorizare și fișiere private

Accesul cere User/web verificat, apartenență validă, capabilitate assets.view și grant pe proprietate. assets.manage cere editare pe proprietate; assets.review are propriul switch și necesită și evidence.review pentru folosirea surselor. Categoriile cer drept de administrare organizațională. PlatformAdmin nu primește automat rol de operator client. Dezactivarea assets.view dezactivează capabilitățile dependente și endpointurile asociate. Toate mutațiile folosesc CSRF, throttling și tranzacții. Niciun ID/UUID furnizat de client nu stabilește singur autorizarea.

Fotografiile PNG/JPEG sunt limitate la 3 × 5 MB per încărcare, 24 megapixeli și 20 de originale per activ, inclusiv cele arhivate. MIME și structura imaginii sunt validate. Originalele folosesc discul privat Evidence, sub `assets/<org>/<asset UUID>/<photo UUID>/original.bin`, cu SHA-256. Nu sunt accesibile prin public/storage. URL-urile sunt semnate zece minute, legate de utilizator și reevaluează drepturile la fiecare acces; conținutul răspunde no-store/nosniff și nu este executabil. Arhivarea/restaurarea foto păstrează originalul și hash-ul. Nu este inclus un antivirus dedicat.

## Ciclul clădirii și retenție

Ștergerea logică a clădirii ascunde activele localizate în ea și blochează pașaportul/fotografiile; restaurarea clădirii readuce aceleași active, fără regenerarea UUID-urilor. Relațiile cu spații arhivate sunt păstrate, dar spațiile retrase nu apar în selecțiile active.

Purjarea explicită din coș, deja autorizată prin fluxul existent, arhivează activele localizate în clădire la nivel de proprietate și le eliberează clădirea/nivelul/spațiul, cu audit. Identitatea activului nu este distrusă odată cu structura clădirii. Legăturile către spații deservite sunt eliminate prin FK, cu eveniment de context. Aprobările care depind de sursele eliminate sunt retrase, FK-urile către afirmații/versiuni devin null, iar identitatea sursei istorice rămâne pentru explicarea indisponibilității. Datele declarate și fotografiile activului aparțin în continuare registrului proprietății, arhivate. Documentele originale ale clădirii urmează cleanup-ul Evidence existent. Toate aceste schimbări sunt în aceeași tranzacție cu purjarea.

## Scalabilitate, verificări și limite

Listele active folosesc paginare de 24; căutarea/filtrele sunt pe server. Lookup-urile întorc maximum 20, inspectorul camerei 8, componentele 12, sursele 15, documentele 12 și istoricul 20 per pagină. Categoria și schema au limite mici explicite. Nu se trimite inventarul complet către JS și nu se includ activele în ciorna clădirii.

Testul reprezentativ creează 10.000 de active într-o organizație, verifică totalul, pagina de 24, filtrarea după serial și lookup de 20; verifică și dimensiuni limitate ale răspunsurilor. Rulările locale SQLite au durat aproximativ 0,5–0,7 secunde pentru inserări și verificările HTTP ale acelui test; aceasta nu este o promisiune de latență pe VPS/MariaDB. Nu există infrastructură MySQL locală disponibilă în această sesiune, iar deploy-ul nu a fost executat pe server.

Verificări în browser cu bază izolată: creare manuală în cameră, editare cu aprobarea păstrată, upload foto și archive/restore foto, legare afirmație/document, aprobare explicită cu diferență față de declarația manuală, matching/import cu date rămase neaplicate, archive/restore activ, creare categorie prin editor vizual și salvare atribut numeric, click cameră în Building Studio, desktop și mobil 390 px, RO/EN. Ultima verificare pe build-ul final nu a avut erori/warnings în consolă sau overflow orizontal pe mobil. Snapshoturile sunt artefacte locale în build, nu date de producție.

Comenzi: `php vendor/bin/phpunit`, `node --test tests/building-generator.mjs`, `npm run build`, Pint pe fișierele PHP modificate, route:cache și view:cache cu cache-uri locale izolate. Instrucțiunile Windows pentru OPENSSL_CONF rămân cele din GENESIS-005. Migrațiile de test rulează exclusiv pe bazele izolate de test; nu se folosește migrate:fresh pe date reale.

Rezultat final local 0.15.0: **206 teste PHP / 1.914 aserțiuni**, toate trecute; 16 teste noi de registru cu 183 aserțiuni. Cele 10 teste ale generatorului Building Studio, build-ul Vite, Pint și verificările cache au trecut. Testul de 10.000 active a durat 0,61 secunde în ultima rulare SQLite, incluzând inserările și verificările HTTP.

Comparația Evidence pentru câmpuri tip string păstrează acum identitatea textuală: seriile `00123` și `123` produc diferență/conflict, iar seria `0` nu este tratată ca absentă. Comparația numerică pentru câmpuri numerice rămâne disponibilă. Regresia este acoperită împreună cu aprobarea explicită a unei serii diferite.

Limite intenționate: un activ și sistemele sale aparțin unei proprietăți, cu mai multe clădiri/spații posibile; transferul între proprietăți și sisteme care traversează proprietăți diferite nu sunt incluse. Matching-ul este determinist, fără deduplicare automată sau presupuneri după producător/model. Atributele personalizate se declară manual; maparea aplicabilă din documente folosește cheile deja existente în Evidence. Mentenanța programată, intervențiile, QR și certificarea profesională rămân viitoare. Nu au fost făcute apeluri AI live sau modificări pe VPS.

Următorul task recomandat: mentenanță preventivă legată de active și spații, cu scadențe, responsabil, checklist/intervenții și dovezi, apoi acces rapid prin QR. Se poate baza pe acest registru fără să creeze o identitate separată pentru echipament.
