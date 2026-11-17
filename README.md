# GNOSIX Building Management

Laravel 13 / Blade / Vite, pentru `https://gnosix.zion3d.ro` pe Laravel Forge. Landing Atlas, identitate client Genesis și administrare separată a platformei. Organizațiile, profilarea multi-tenant și Workspace Studio sunt disponibile, cu proprietăți, clădiri, etaje și spații operaționale. Building Studio adaugă declarații persistente, machetă axonometrică și istoric. Evidence Engine oferă documente private/versionate, afirmații cu proveniență, review și conflicte. Smart Asset Registry adaugă pașapoarte și echipamente legate de spații/sisteme și dovezi. Mentenanța conectează sesizări, ordine de lucru, planuri preventive și dovezi. Release 0.17 adaugă QR securizat, acces extern limitat și portal de teren cu rapoarte PDF versionate. Building DNA 0.18 conectează structura și activele într-un graf tipizat, cu proveniență, review și simulare explicabilă a impactului potențial.

## Checklist de livrare

`[x]` înseamnă implementat și verificat; `[ ]` înseamnă planificat sau incomplet. Acest checklist se actualizează la fiecare task. O demonstrație vizuală nu înseamnă că modulul operațional este livrat.

### Building DNA — 0.18.0

- [x] Audit 0.17; Laravel/Blade/Vite, autentificarea, tenant/granturile, Asset Registry, Maintenance și Evidence păstrate.
- [x] Adaptor SQL pentru structura existentă, localizare, componente și spații deservite; fără registru sau graf de adevăr paralel.
- [x] Noduri cu UUID stabil pentru proprietate/clădire/nivel/spațiu/activ/sistem; UUID aditiv și backfill numai pentru Property.
- [x] Relații funcționale tipizate și tipuri custom configurabile, sens/cardinalitate/propagare/cicluri centralizate; conexiunile generice nu propagă defecțiuni.
- [x] Declarații/propuneri/review/contestare/retragere/repropunere cu revizii, motive, audit și capete imuabile; serves folosește pivotul original.
- [x] Explorer Cytoscape lazy RO/EN: zoom/pan/fit, selecție, inspector, căutare, extindere/paginare, grupuri, filtre și evidențiere; listă accesibilă actualizată și mobil/reduced-motion.
- [x] Simulare read-only cu trasee explicabile, spații/echipamente potențial afectate, direcții semantice, redundanță/incertitudine, deduplicare și bugete de adâncime/noduri/timp.
- [x] Referințe la versiunile/afirmațiile Evidence existente, pagină sursă, bază explicită de review și semnalarea surselor istorice/modificate/lipsă; fără confirmare sau AI automat.
- [x] Observații deterministe pe proprietate/clădire, cu referințe, review/dismiss/resolve justificat și decizii istorice; informația lipsă nu este prezentată ca defect.
- [x] Legături din Building Studio, Asset Passport și ordinul de mentenanță; QR și toate fluxurile existente păstrate.
- [x] Capabilități DNA și autorizare server pentru fiecare endpoint; surse private redactate fără permisiune Evidence, CSRF/rate limiting/tranzacții și protecții de concurență.
- [x] Bugete configurabile în Admin → Building DNA, în DB și cu autentificare recentă; fără setări business .env noi.
- [x] 286 teste PHP / 2.428 aserțiuni și 17 scenarii JS trecute; dataset 10.000 active / 2.500 relații, build, lint/Pint/cache și browser desktop/mobil RO/EN.
- [x] Documentație arhitectură/securitate/limite/deploy și pachet Forge 0.18.0 cu checksum.
- [ ] Smoke pe VPS/MariaDB/MySQL, corpus tehnic real și validare asistivă pe dispozitive reale; acestea nu au fost executate local.
- [ ] Time Machine operațional, calcule inginerești/capacități, indisponibilitate PMS și IoT fizic rămân dezvoltări viitoare.

Arhitectură, fișiere, rezultate și limite: [DNA-018.md](docs/DNA-018.md). Deploy: [DEPLOY-FORGE.md](docs/DEPLOY-FORGE.md).

### Smart QR, Secure Access & Technician Portal — 0.17.0

- [x] Audit 0.16; identitate web, permisiuni, WorkOrder/Workflow, Asset Registry și Evidence reutilizate, fără sisteme paralele.
- [x] UUID QR permanent pentru clădiri, niveluri, spații și active/sisteme tehnice; locator public opac, generare/rotire/revocare, PNG/SVG, etichete GNOSIX și print preview în lot.
- [x] QR anonim fără metadate; rezolvare cu autorizare curentă, arhivare/restaurare fără pierderea identității și audit lifecycle.
- [x] Invitații cu token hash, răscumpărare idempotentă pentru un singur cont, expirare, revocare, email opțional de legare și scop pe ordine/activ; fără membership sau abonament obligatoriu.
- [x] Firme externe prin invitații individuale și identități verificate; documente explicit partajate, fără acces general Evidence sau note interne.
- [x] Portal mobil RO/EN: lucrări autorizate, localizare/activ, checklist, foto nativă, materiale, timp, costuri permise, ciornă/reluare și trimitere cu declarație.
- [x] Rapoarte append-only draft/submitted/reviewed/accepted/returned, PDF privat versionat, verificare manager, surse/istoric în Asset Passport și Building Memory.
- [x] Observații deterministe din istoricul autorizat, garanție/manual și contradicții de serie; candidați Evidence separați de faptele confirmate, fără apel AI nou.
- [x] CSRF/throttling, fișiere semnate și reautorizate, headere private, scop tenant, tranzacții/revizii, retragere la anulare/pierderea autorizării/purjare.
- [x] Durata invitațiilor configurabilă în Admin → QR & Tehnicieni, în DB și cu recent admin authentication; fără setări business noi în .env.
- [x] 250 teste PHP / 2.235 aserțiuni și 10 scenarii JS trecute; build, audit Composer, lint/Pint/cache și browser desktop/mobil RO/EN; registru QR cu 10.000 active, PDF-uri randate și inspectate.
- [x] Documentație de arhitectură/securitate/deploy și pachet Forge 0.17.0 cu checksum, surse și frontend compilat.
- [ ] Smoke deploy pe VPS/MariaDB/MySQL, SMTP real și cameră/imprimantă fizice; nu au fost executate în QA local.
- [ ] SLA/escaladări, calendar avansat, PWA/offline și semnătură calificată sunt dezvoltări ulterioare.

Detalii, rezultate, fișiere și limite: [FIELD-017.md](docs/FIELD-017.md). Deploy: [DEPLOY-FORGE.md](docs/DEPLOY-FORGE.md).

### Maintenance Intelligence & Work Orders — 0.16.0

- [x] Audit al implementării 0.15; păstrarea Laravel/Blade/Vite, Asset Registry, Evidence și permisiunilor existente.
- [x] Sesizări contextualizate din clădire, cameră, activ și centrul de mentenanță, cu ciornă/trimitere, simptome, severitate, impact și atașamente private.
- [x] Ordine distincte și relație multe-la-multe cu sesizările; alocare, priorități, termene și stări flexibile cu motive, redeschidere și control de revizie.
- [x] Execuție cu checklist, lucrări, materiale/componente și costuri nullable; verificare/închidere de coordonator cu autor/timestamps și audit append-only reutilizat.
- [x] Roluri Raportor/Tehnician configurabile, granturi pe proprietate, note private și documente generale restricționate; atribuirea unui ordin nu extinde accesul Evidence.
- [x] Planuri recurente zilnice/săptămânale/lunare/trimestriale/anuale/custom, suspendare/reluare, istoric de execuție și generare idempotentă prin scheduler/coadă.
- [x] Notificare SMTP opțională per plan, după commit; usage/IoT reprezentat explicit ca viitor, fără intervale legale inventate.
- [x] Integrare cu versiunile/afirmațiile/conflictele existente, candidați documentari revizuibili și istoric al activului, fără suprascriere automată sau apel AI obligatoriu.
- [x] Observații repetitive deterministe cu trimiteri și prag/fereastră configurabile în administrare, în DB; fără setări business noi în .env.
- [x] Command Center RO/EN: metrici reale, listă/panou paginat, filtre de portofoliu/proprietate/clădire, căutare server-side, costuri pe monedă și focus/reduced-motion.
- [x] 230 teste PHP / 2.075 aserțiuni și 10 verificări JS trecute; build production, Pint/lint/cache, browser desktop/mobil, documentație și arhivă 0.16.0.
- [ ] Smoke deploy pe VPS/MariaDB/MySQL, worker maintenance și livrare SMTP reală; acestea nu au fost executate local.
- [x] QR și acces extern pentru tehnicieni/firme sunt livrate în 0.17.0; SLA/escaladări, calendar avansat și IoT rămân planificate.

Arhitectură, fișiere, reguli și limite: [MAINTENANCE-016.md](docs/MAINTENANCE-016.md).

### Smart Asset Registry — 0.15.0

- [x] Audit al stackului și arhitecturii existente; Laravel 13 / Blade / Vite, fără migrare la Vue/Inertia sau motor Evidence duplicat.
- [x] Active fizice universale cu UUID/referință stabile, proprietate, localizare opțională clădire/nivel/spațiu, sisteme și spații deservite în mai multe clădiri ale proprietății.
- [x] Categorii și atribute configurabile în organizație, editor vizual, tipuri validate, preseturi pentru toate categoriile cerute; valori necunoscute păstrate null.
- [x] Date manuale pentru producător/model/serie, instalare, achiziție, garanție, stare și note; fotografii private validate, semnate și restituibile după arhivare.
- [x] Asset Passport Studio RO/EN, layout responsive, focus/controale accesibile, motion redus, date secundare extensibile și istoric de modificări.
- [x] Asociere explicită cu afirmații/versiuni Evidence existente și aprobări documentare separate, cu justificare, revizii, conflicte vizibile și fără suprascriere silențioasă.
- [x] Import asistat din documentele deja procesate: potriviri după serie/identificator, semnalare duplicate, confirmare înainte de creare/asociere; producătorul/modelul nu stabilesc singure identitatea.
- [x] Building Studio încarcă echipamentele camerei la click, inclusiv sistemele comune care o deservesc, paginat și fără inventar complet în snapshot.
- [x] Politici/granturi și capabilități view/manage/review/types, CSRF/throttling, tranzacții, audit, archive/restore și integrare sigură cu coșul/purjarea clădirilor.
- [x] Dataset de 10.000 active: căutare/filtre/paginare pe server, 24 carduri și lookup-uri de 20; teste pentru scenarii, tenant, seriale, dovezi, arhivare, fotografii și retenție.
- [x] Teste și build production, verificare browser desktop/mobil RO/EN, documentație și pachet Forge actualizate; fără dependențe noi.
- [ ] Validare de deploy pe VPS/MySQL/MariaDB și transfer între proprietăți; QR este livrat în 0.17.0, iar mentenanța/intervențiile în 0.16.0.

Arhitectură, fișiere, retenție și limite: [SMART-ASSET-REGISTRY.md](docs/SMART-ASSET-REGISTRY.md).

### Furnizori AI alternativi și modele gratuite — 0.14.0

- [x] OpenAI păstrat; adaptoare reale Gemini, OpenRouter și Ollama local, prin registru central și contract de extracție comun.
- [x] Furnizor/model/cheie configurabile din administrare; chei criptate separat, configurația OpenAI existentă adoptată fără migrare, fără chei noi în .env.
- [x] OpenRouter cu mod „Doar modele gratuite” activ implicit: openrouter/free sau :free, validare server și fără fallback automat la modele plătite/alți furnizori.
- [x] Gemini pentru PDF/PNG/JPEG; OpenRouter și Ollama pentru PNG/JPEG, cu refuz explicit al PDF-urilor și păstrarea fluxului manual.
- [x] Ollama fără API key, endpoint loopback fix, verificare model vision local înaintea transmiterii documentului, refuzul aliasurilor cloud și fără descărcare automată de modele.
- [x] Consimțământ per versiune, coadă/retry/review existente păstrate; schimbarea furnizorului/modelului invalidează cererea veche înainte de transmitere.
- [x] Teste automate pentru payloaduri, chei, gratuitate, erori de cotă, formate, alias cloud și setări; browser desktop/mobil fără overflow sau erori de consolă.
- [ ] Validare live cu cheile operatorului și modelele disponibile în cont; gratuitatea/cotele/politica datelor depind de furnizor. Ollama necesită instalare și resurse pe server.

Instrucțiuni: [AI-PROVIDERS.md](docs/AI-PROVIDERS.md).

### Genesis Task 005 · Evidence Engine — 0.13.0

- [x] Audit al arhitecturii existente înainte de modificare; reutilizarea identității web, granturilor pe proprietate, capabilităților și layoutului Blade.
- [x] Documente PDF/PNG/JPEG private, tip declarat, versiuni originale imuabile, SHA-256, deduplicare per clădire, limite de fișiere/cotă și acces semnat cu expirare plus autorizare la fiecare cerere.
- [x] Afirmații tipizate separate de identitatea manuală: clădire/spațiu/echipament identificat, sursă, versiune, pagină/zonă/citat, metodă, timestampuri și dimensiuni distincte de încredere/verificare.
- [x] Propunere, confirmare, respingere, investigare, corectare prin propunere nouă și înlocuire justificată; conflicte deterministe fără suprascriere silențioasă, istoric și audit.
- [x] Review Studio RO/EN: previzualizare PDF.js cu pagini/highlight când există coordonate, imagini, carduri, proveniență, comparație, review în lot doar fără conflicte, inventar și cronologie.
- [x] Interfață de extracție independentă de furnizor și adapter real OpenAI Responses, coadă database dedicată, retry/idempotence, validare strictă și nicio confirmare automată.
- [x] Setări AI criptate în administrarea platformei, notificare și consimțământ explicit per versiune; fără chei noi de produs în .env. Fără configurare, fluxul manual funcționează și AI este afișat indisponibil.
- [x] Arhivarea documentelor păstrează sursele. Ștergerea/restaurarea clădirii păstrează memoria; purjarea autorizată folosește o coadă tranzacțională de ștergere a fișierelor, cu cleanup reluabil.
- [x] Teste de upload, tenant/granturi/capabilități, extracție indisponibilă/eșuată/reluată, worker database real cu provider de test, conflicte, review, integritate și retenție; build și browser desktop/mobil RO/EN.
- [ ] Validare cu furnizorul AI live și corpus de documente reale după configurarea modelului/cheii; scanare antivirus dedicată și flux profesional de verificare independentă.

Implementare, fișiere, limite și verificări: [GENESIS-005.md](docs/GENESIS-005.md). Configurare VPS: [DEPLOY-FORGE.md](docs/DEPLOY-FORGE.md).

### Ștergere definitivă și portofoliu vizual — 0.12.0

- [x] Ștergere definitivă individuală din coșul de gunoi, cu dialog în aceeași pagină, explicație ireversibilă și Anulează/Escape.
- [x] Purjare tranzacțională doar pentru clădiri deja șterse: structură activă/arhivată, paturi, unități, legături, ciorne, afirmații și revizii; alte clădiri/proprietăți sunt păstrate.
- [x] Verificare server pentru confirmare, versiune, acces și capabilități; cheile externe rămân active și legăturile externe neașteptate anulează integral operațiunea.
- [x] Proprietăți afișate în carduri ca în biblioteca de clădiri, cu click/tastatură, nume, tip, număr de clădiri active și date de localizare cunoscute.
- [x] Nouă ilustrații SVG originale, distincte după tip: casă, cabană, pensiune, hostel, hotel, resort, apartamente, mixt și altul; assets locale, lazy loading, fără dependențe noi.
- [x] RO/EN, teste automate, build și verificări în browser desktop/mobil pentru carduri, dialog, anulare și ștergerea unei clădiri fictive locale.

### Coș de gunoi și dialog de ștergere — 0.11.0

- [x] Coș de gunoi accesibil din Camere și Spații, cu paginare și clădirile șterse vizibile conform accesului la proprietate.
- [x] Restaurare tranzacțională cu aceleași UUID-uri, etaje, camere, paturi, unități, legături și date; funcționează și pentru ștergerile existente din 0.10.1.
- [x] Restaurarea folosește structura de la ultima ștergere; elementele arhivate înaintea ștergerii rămân arhivate. Starea anterioară a clădirii este păstrată.
- [x] Permisiuni/capabilități și versiuni verificate server-side, restaurare refuzată fără istoric complet, operațiuni atomice și audit pentru cicluri repetate ștergere/restaurare.
- [x] Confirmarea ștergerii este dialog modal în Camere și Spații, cu Anulează/Escape și focus accesibil; pagina și ruta separate de confirmare au fost eliminate.
- [x] RO/EN, verificări automate și în browser pentru dialog, coș, restaurare, Studio și mobil; fără migrații sau dependențe noi față de 0.10.1.

### Ștergerea clădirilor — 0.10.1

- [x] Buton Șterge clădirea în Camere și Spații, doar pentru utilizatorii care pot edita proprietatea.
- [x] Confirmare RO/EN cu numele/referința clădirii, explicația efectului și Anulează / Da, șterge clădirea; GET și anularea nu modifică date.
- [x] Ștergere logică tranzacțională: clădirea dispare din liste/Studio, spațiile/nivelurile/paturile/unitățile sunt retrase din fluxurile active; UUID-urile, legăturile și istoricul sunt păstrate.
- [x] Validare server, CSRF, permisiuni/tenant/capabilități, verificarea versiunii și blocarea ciornelor vechi după ștergere; referințele șterse rămân rezervate.
- [x] Migrare aditivă deleted_at, teste automate, build și verificare în browser pentru anulare și confirmare pe date locale de test.

### Building Studio unificat — 0.10.0

- [x] Camere și Spații devine biblioteca de clădiri a organizației: căutare, filtre după proprietate/stare, paginare și acces conform apartenenței/granturilor.
- [x] Click pe clădire deschide direct Building Studio pentru editori; viewerii explorează dashboardul fără drepturi de modificare.
- [x] Formularele separate pentru clădiri, niveluri și camere sunt înlocuite în interfață cu Studio; linkurile vechi către camere deschid inspectorul din Studio.
- [x] Generator cu nivel de început/parter/subsol, camere per nivel, suprafață m², paturi și capacitate per cameră; sumar live. Exemplu verificat: 5 × 10 camere × 15 m² / 2 paturi / 2 locuri.
- [x] Extindere prin niveluri noi sau înlocuire explicită a ciornei, cu undo, autosave și păstrarea identităților la editare.
- [x] Click/Enter pe cameră deschide inspectorul: nume, tip, număr, paturi, locuri, suprafață și mutare la alt nivel. Salvarea clădirii este disponibilă direct din bara persistentă.
- [x] Spațiile vechi fără clădire pot fi asociate explicit unei clădiri din aceeași proprietate, cu UUID/date păstrate și audit.
- [x] RO/EN, etichete pe plan și carduri, generator restrâns în modul editare, focus și filtre păstrate la schimbarea limbii.
- [x] Șase teste PHP noi, zece teste JS pentru generator, build production și verificări în browser desktop/mobil/tastatură.

### Corecție deploy Forge — 0.9.1

- [x] Indexul ciornelor Building Studio are nume explicit compatibil MySQL.
- [x] Migrarea întreruptă se poate relua fără regenerarea UUID-urilor, pierderea datelor/ciornelor sau duplicarea istoricului; indexurile și cheile externe lipsă sunt refăcute.
- [x] Trei teste automate de recuperare; eroarea originală 1059 și recuperarea au fost verificate separat pe MySQL 8.4.13 într-o bază temporară, inclusiv salvare și retry după recuperare.
- [x] Instrucțiuni de redeploy și arhivă Forge 0.9.1 actualizate. Deploy-ul pe VPS rămâne de executat în Forge.

### Fundație, landing și administrarea platformei

- [x] Repository Git inițializat, `.gitignore` Laravel, origin GitHub și livrare prin commit/push.
- [x] Cinci concepte HTML locale, selecție Atlas și integrare Laravel/Blade/Vite.
- [x] Identitate premium bleumarin/violet/lime, fonturi locale, responsive și suport pentru motion redus.
- [x] Landing RO implicit `/`, EN `/en`, confidențialitate în ambele limbi.
- [x] Contact direct în pagină: nume, email, telefon, maximum șase subiecte și mesaj; primul subiect selectat, validare server email/telefon, feedback și protecție anti-abuz.
- [x] SEO: canonical, hreflang, x-default, sitemap, JSON-LD, Open Graph/Twitter și imagini sociale localizate.
- [x] Demonstrații interactive de prezentare: Time Machine, Evidence Engine, Building DNA și checklist Handover, marcate cu date fictive.
- [x] Admin distinct de clienți: model/guard/cookie proprii, login și passkeys WebAuthn.
- [x] Comanda `gnosix:admin` pentru inițializare, resetare explicită și revocare passkeys; documentație Forge.
- [x] Setări General, GNOSIX · Site & contact, SMTP și Securitate; setări de produs în DB, cu import unic din fostele variabile GNOSIX.
- [x] SMTP criptat în DB, transport central pentru toate emailurile, test de livrare către administrator.
- [x] Protecții admin: reconfirmare, rate limiting, criptare și invalidarea sesiunilor.
- [x] Deploy Forge cu build, migrații și verificări înainte de activare; arhivă fără secrete/runtime.

### Genesis 001 — Identity, Authentication & Premium Experience

- [x] Audit înainte de implementare și reutilizarea User, guard `web` și brokerului Laravel.
- [x] Login, înregistrare, logout și verificare email obligatorie prin link semnat și expirabil.
- [x] Recuperare/resetare parolă cu token hash-uit, expirabil, de unică folosință și feedback neutru.
- [x] Profil personal, preferință RO/EN, schimbare email cu reconfirmare și schimbare parolă.
- [x] Sesiuni regenerate/versionate, revocarea celorlalte dispozitive, rate limiting și CSRF.
- [x] Detectarea primei autentificări, introducere Genesis, shell protejat și reluarea fluxului corect.
- [x] Componente/layout reutilizabile, stări de eroare/loading, accesibilitate, formulare fără JS și motion redus.
- [x] Separare completă admin/client și regresii automate pentru autentificare, SMTP și passkeys.

### Genesis 002 — Smart Client Profiling & Capability Engine

- [x] Audit Task 001 înainte de modificări; păstrarea conturilor și a profilului personal existent.
- [x] Șase tabele aditive: organizations, organization_memberships, organization_profiles, organization_capabilities, user_preferences, onboarding_sessions.
- [x] Mai multe organizații per utilizator și mai mulți utilizatori per organizație; selecție activă persistentă și switching autorizat.
- [x] Roluri owner/manager/member, administrarea membrilor cu cont confirmat, revocare și protecția proprietarului.
- [x] Profiluri Hospitality, Construction & Development și Property & Facility Management în registru și UI; numai Hospitality selectabil.
- [x] Hospitality: operator independent, companie/organizație sau portofoliu/lanț, cu formular progresiv și informații minime.
- [x] Carduri cu ilustrații arhitecturale, exemple, explicații accesibile, preview de capabilități, selecție animată și RO/EN.
- [x] Profilul organizației independent de tipul clădirilor; recomandări fără restricții de proprietate.
- [x] Registru central pentru profile, scheme, capabilități, recomandări, navigare și vizibilitate.
- [x] Policy de organizație, context verificat la fiecare cerere și refuz server-side pentru capabilități inactive/necunoscute.
- [x] Opțiuni de capabilități per organizație în DB; dezactivarea elimină accesul și linkul, fără ștergerea datelor.
- [x] Salvare automată și manuală a ciornei, reluare după refresh, control al versiunii și finalizare idempotentă.
- [x] Schimbarea profilului/formei fără ștergerea organizației, membrilor, datelor sau opțiunilor anterioare; test de upgrade viitor cu clădiri reale și dovezi-fixture.
- [x] UI pentru organizații/setări/membri și pagini de context al modulelor; mentenanța devine operațională în 0.16.0; dovezile sunt operaționale din Task 005; proprietățile/spațiile devin operaționale în Task 003.
- [x] Teste de autorizare, IDOR, izolare între utilizatori/organizații, CSRF, profile indisponibile, concurență și regresii Task 001.

### Genesis 003 — Adaptive Workspace & Hospitality Onboarding

- [x] Audit Task 002; arhitectura Blade/Vite, autentificarea și politicile existente reutilizate, fără dependențe noi.
- [x] Workspace Studio RO/EN cu șase scenarii: casă/cabană, pensiune, hostel, hotel, resort și grup hotelier.
- [x] Motor condițional central PHP/JSON: întrebări, dependențe, valori sugerate, validare și preview din aceeași schemă.
- [x] Flux minim pentru o cabană; configurare progresivă de clădiri, etaje, numerotare, facilități, dormitoare și număr de paturi.
- [x] Proprietăți multiple per organizație; clădiri și etaje opționale; spații direct în proprietate sau în clădire/etaj.
- [x] Creare, listare și editare proprietăți, clădiri, etaje și spații; camere, apartamente, dormitoare, zone tehnice/comune și alte tipuri.
- [x] Grupuri/branduri/portofolii multiple per proprietate și entități juridice opționale per proprietate.
- [x] Setări organizație în DB: nume, denumire juridică, țară, fus orar, limbă, unități și contact; suprafețe normalizate în m² și afișate în m²/ft².
- [x] Logo raster privat, validare dimensiune/tip, livrare autorizată și înlocuire/eliminare din setări.
- [x] Roluri owner/admin/member și compatibilitate manager; granturi viewer/editor pe proprietăți, inclusiv pentru administratori restricționați.
- [x] Ciorne separate per utilizator/organizație, autosave/manual, mod ghidat opțional, preview live, progres și explicații contextuale.
- [x] Back/forward și refresh păstrează răspunsurile; valori ascunse păstrate în ciornă, excluse din generare; concurență optimistă și confirmare idempotentă.
- [x] Politici server-side pentru fiecare organizație/proprietate și relație imbricată; dezactivarea capabilităților refuză accesul direct.
- [x] Teste pentru toate cele șase scenarii, izolare tenant, granturi/revocare, validare, CSRF, limite, fișiere și regresii; verificare browser desktop/mobil.

### Genesis 004 — Smart Building Declaration Studio

- [x] Audit Task 003 și evaluare SVG.js/Konva; implementare SVG nativă, fără framework sau dependențe suplimentare.
- [x] Nouă preseturi flexibile: casă, cabană, pensiune, hostel, hotel, resort, bloc de apartamente, clădire mixtă și tip personalizat; clasificarea poate rămâne necunoscută.
- [x] UUID stabil pentru clădiri/niveluri/spații, referință lizibilă, nume intern și de prezentare, clasificare, adresă/coordonate, țară/fus orar și an opțional.
- [x] Migrare aditivă cu backfill al identităților, fără substituirea informațiilor necunoscute cu date inventate.
- [x] Studio RO/EN: propuneri explicite, machetă axonometrică explodată, plan schematic, selecție nivel/spațiu, inspector și dashboard dedicat.
- [x] Adăugare/copiere/reordonare/eliminare niveluri, subsol/mezanin/mansardă, spații tehnice/comune, nume și numerotare individuală sau pe nivel.
- [x] Spații fizice independente de unitățile de cazare; suite cu mai multe spații, cabină întreagă și dormitoare/paturi individuale.
- [x] Ciorne personale per proprietate/clădire, autosave, reluarea etapei, undo local, validare server și protecție împotriva salvărilor concurente.
- [x] Declarație draft/active/archived; editare ulterioară, arhivare nedistructivă a structurii și recuperare prin JSON cu aceleași identități.
- [x] Istoric append-only, autor/moment/sursă, snapshot înainte/după, afirmații declarate neverificate și câmp separat pentru referințe documentare viitoare.
- [x] Formularele clasice sunt păstrate și auditate; Workspace Studio înregistrează structura inițială o singură dată per clădire.
- [x] Import/export JSON GNOSIX v1; import ca șablon cu identități noi, salvare în tranzacție și operații DB în loturi.
- [x] Izolare server pe organizație/proprietate, refuz pentru UUID-uri străine și capabilități dezactivate; CSRF/rate limiting existente păstrate.
- [x] Navigare mobilă compactă, plan/listă alternativă, tastatură, motion redus și randare limitată la 12 niveluri/40 carduri de spații simultan.
- [x] Teste pentru toate scenariile cerute, 5.000 spații, migrarea datelor existente, istoric/recuperare, securitate și regresii; verificare browser desktop/mobil.

### Status module și etape următoare

- [ ] Activarea profilului Construction & Development și a fluxurilor sale.
- [ ] Activarea profilului Property & Facility Management și a fluxurilor sale.
- [ ] Arhivarea proprietăților/grupurilor, politici automate de retenție și mutarea spațiilor între proprietăți.
- [x] Registru operațional de echipamente și pașapoarte — livrat în 0.15.0.
- [x] Mentenanță, intervenții și planuri preventive operaționale — 0.16.0.
- [x] QR și portal de teren cu invitații limitate — 0.17.0.
- [ ] Calendar avansat și SLA.
- [x] Încărcarea/stocarea/versionarea și revizuirea documentelor/dovezilor reale — livrate în Task 005; extracția live necesită configurarea furnizorului.
- [x] Evidence Engine operațional: proveniență și detectarea contradicțiilor — Task 005, extins prin rapoartele 0.17.0.
- [x] Building DNA cu relații reale între active — 0.18.0.
- [ ] Time Machine operațional, bazat pe istoricul verificabil al datelor.
- [ ] Trust Passport, Zero-Entry asistat AI, Handover între organizații și LifeCycle Simulator.
- [ ] Invitații prin email, transfer de proprietar și delegare granulară dincolo de proprietate.
- [ ] Passkeys pentru clienți, social login și 2FA.
- [ ] Geometrie măsurată/CAD/BIM și import IFC, API public, integrări IoT și ecosistem furnizori.
- [ ] Rezervări, tarife, disponibilitate și operațiuni PMS pentru unitățile de cazare declarate.
- [ ] Verificarea acestui release pe VPS și a livrării în inbox după deploy.

## Instalare locală

PHP 8.4, Composer, Node 22.12+ sau 24 LTS, npm și o bază de date (SQLite local sau MySQL/PostgreSQL).

```powershell
composer install
# Numai dacă .env nu există deja:
Copy-Item .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
npm ci
npm run build
php artisan serve --host=localhost --port=8035
```

Configurează în `.env` `APP_URL=http://localhost:8035`, `PASSKEY_ORIGIN=http://localhost:8035`, `PASSKEY_RP_ID=localhost`, apoi completează `ADMIN_EMAIL` și `ADMIN_PASSWORD` (minimum 12 caractere) și rulează:

```powershell
php artisan gnosix:admin
```

Acces: `http://localhost:8035/admin/login`. În admin salvează SMTP și adaugă passkey din tabul Securitate. Din **GNOSIX · Site & contact** configurează destinatarul, adresa canonică și activarea contactului/indexării. La instalare nouă, formularul și indexarea sunt oprite până le activezi din administrare.

Pentru un `.env` mai vechi folosește `SESSION_DRIVER=file`, `CACHE_STORE=file` și `QUEUE_CONNECTION=sync`. Adminul necesită în continuare bază de date și migrații. Nu există creare publică de conturi platform admin.

## Documentație

- [Building Studio unificat — bibliotecă, generator și editare](docs/BUILDING-STUDIO-WORKFLOWS.md)

- [Genesis 004 — Building Studio, identități și recuperare](docs/GENESIS-004.md)
- [Audit Genesis 004 și alegerea vizualizării](docs/GENESIS-004-AUDIT.md)

- [Genesis 003 — Workspace Studio, structură și permisiuni](docs/GENESIS-003.md)
- [Audit Genesis 003](docs/GENESIS-003-AUDIT.md)

- [Genesis 002 — organizații, capabilități și reluare onboarding](docs/GENESIS-002.md)
- [Audit Genesis 002](docs/GENESIS-002-AUDIT.md)
- [Genesis 001 — identitate, securitate, deploy și fișiere modificate](docs/GENESIS-001.md)
- [Audit arhitectural Genesis 001](docs/GENESIS-001-AUDIT.md)
- [Administrare, SMTP, configurare passkey și recuperare](docs/ADMIN.md)
- [Publicare pe Laravel Forge](docs/DEPLOY-FORGE.md)
- [Formular de contact, traduceri și SEO](docs/CONTACT-AND-SEO.md)
- [Setări de sistem și migrarea vechilor valori din mediu](docs/SYSTEM-SETTINGS.md)
- [Experiența interactivă și limitele demonstrației](docs/LANDING-EXPERIENCE.md)
- [Brief-ul și explorările inițiale](docs/DESIGN-BRIEF.md)

Parolele nu se trimit ca argumente Artisan și nu se pun în Git. După provisioning, `ADMIN_PASSWORD` poate fi eliminată din mediu. Loginul folosește hash-ul din baza de date; schimbarea valorii din mediu nu resetează automat parola. Folosește explicit `gnosix:admin --reset-password` când este necesar.

## Verificare

```powershell
php vendor/bin/phpunit
node tests/building-generator.mjs
npm run build
composer validate --no-check-publish
php artisan gnosix:check
```

163 teste / 1.561 de aserțiuni au verificat declarațiile de clădiri, paturile/unitățile independente, migrarea, arhivarea/recuperarea, importul de 5.000 spații, cele șase scenarii Workspace Studio, structura proprietăților, accesul per proprietate, logo-urile private, unitățile, organizațiile și apartenențele, profilarea, capabilitățile, izolarea tenantului, ciornele, setările de sistem și importul lor, contactul bilingv, emailul și telefonul, SEO, izolarea admin/client, provisioning-ul, criptarea și aplicarea SMTP globală, precum și înregistrarea și loginul WebAuthn cu semnături ES256 reale generate de un autentificator sintetic. Testele resping origini/RP ID/challenge/semnături incorecte, lipsa verificării utilizatorului, credențiale revocate și reutilizarea challenge-ului.

Au fost verificate în browser loginul cu parolă, setările SMTP, un test de email către un SMTP sink local, logoutul și paginile mobile. Nu a fost înregistrată o cheie pe dispozitivul real al utilizatorului și nu s-a trimis email extern. Pentru Task 003 s-au verificat suplimentar în browser autosave, refresh, back/forward, creare structură și spațiu comun, RO/EN, paginile de setări și viewport-uri de 1440 și 390 px. Verificarea finală a frontend-ului nu a raportat erori de consolă. Pentru Task 004 s-au verificat și editarea/salvarea declarației, suită cu două spații, spațiu tehnic, export descărcat și reimportat ca șablon, RO/EN, mobile/reduced-motion și navigarea până la nivelul 100 al clădirii cu 5.000 spații. Infrastructura Forge nu a fost modificată.

Pe Windows, dacă OpenSSL nu poate genera cheile sintetice, setează `OPENSSL_CONF` către `extras/ssl/openssl.cnf` din instalarea PHP. `php vendor/bin/phpunit` evită limita de dimensiune a mediului la subprocesele create de `artisan test` pe acest host.

## Publicare și pachet

`.env.production.example` și `deploy/forge-zero-downtime.sh` pregătesc instanța Forge. După primul deploy rulează `php artisan gnosix:admin`, configurează SMTP din admin, înregistrează passkey și verifică pe domeniul final. `gnosix:check --production` permite inițializarea înainte de SMTP; `gnosix:check --launch` verifică suplimentar pilotul și indexarea.

```powershell
python scripts/package-release.py
```

Arhiva `build/gnosix-atlas-forge-0.18.0.zip` include sursele, traducerile, frontend-ul compilat și documentația, fără `.env`, `vendor`, baze de date, loguri sau sesiuni. Necesită Composer pe server. Forge preia în mod normal codul din Git.

Modelele HTML sunt păstrate în `design/concepts`, în afara document root-ului. Sursele landing-ului sunt în `resources/views/landing`, iar administrarea în `resources/views/admin` și `app/Http/Controllers/Admin`. Fonturile sunt locale, cu licența inclusă. Datele clădirii din landing sunt demonstrative.
