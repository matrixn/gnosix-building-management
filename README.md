# GNOSIX Building Management

Laravel 13 / Blade / Vite, pentru `https://gnosix.zion3d.ro` pe Laravel Forge. Landing Atlas, identitate client Genesis și administrare separată a platformei. Organizațiile, profilarea multi-tenant și Workspace Studio sunt disponibile, cu proprietăți, clădiri, etaje și spații operaționale. Building Studio adaugă declarații persistente, machetă axonometrică și istoric. Mentenanța și dovezile sunt în dezvoltare.

## Checklist de livrare

`[x]` înseamnă implementat și verificat; `[ ]` înseamnă planificat sau incomplet. Acest checklist se actualizează la fiecare task. O demonstrație vizuală nu înseamnă că modulul operațional este livrat.

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
- [x] UI pentru organizații/setări/membri și pagini de context al modulelor; mentenanța și dovezile rămân pagini de context; proprietățile/spațiile devin operaționale în Task 003.
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

### Etape următoare — neimplementate operațional

- [ ] Activarea profilului Construction & Development și a fluxurilor sale.
- [ ] Activarea profilului Property & Facility Management și a fluxurilor sale.
- [ ] Arhivarea proprietăților/grupurilor, politici de retenție/ștergere definitivă și mutarea spațiilor între proprietăți.
- [ ] Mentenanță, echipamente, intervenții, calendar și QR în aplicația operațională.
- [ ] Încărcarea/stocarea/versionarea și revizuirea documentelor/dovezilor reale.
- [ ] Evidence Engine operațional: proveniență și detectarea contradicțiilor.
- [ ] Time Machine operațional și Building DNA cu relații reale între active.
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

154 teste / 1.440 de aserțiuni au verificat declarațiile de clădiri, paturile/unitățile independente, migrarea, arhivarea/recuperarea, importul de 5.000 spații, cele șase scenarii Workspace Studio, structura proprietăților, accesul per proprietate, logo-urile private, unitățile, organizațiile și apartenențele, profilarea, capabilitățile, izolarea tenantului, ciornele, setările de sistem și importul lor, contactul bilingv, emailul și telefonul, SEO, izolarea admin/client, provisioning-ul, criptarea și aplicarea SMTP globală, precum și înregistrarea și loginul WebAuthn cu semnături ES256 reale generate de un autentificator sintetic. Testele resping origini/RP ID/challenge/semnături incorecte, lipsa verificării utilizatorului, credențiale revocate și reutilizarea challenge-ului.

Au fost verificate în browser loginul cu parolă, setările SMTP, un test de email către un SMTP sink local, logoutul și paginile mobile. Nu a fost înregistrată o cheie pe dispozitivul real al utilizatorului și nu s-a trimis email extern. Pentru Task 003 s-au verificat suplimentar în browser autosave, refresh, back/forward, creare structură și spațiu comun, RO/EN, paginile de setări și viewport-uri de 1440 și 390 px. Verificarea finală a frontend-ului nu a raportat erori de consolă. Pentru Task 004 s-au verificat și editarea/salvarea declarației, suită cu două spații, spațiu tehnic, export descărcat și reimportat ca șablon, RO/EN, mobile/reduced-motion și navigarea până la nivelul 100 al clădirii cu 5.000 spații. Infrastructura Forge nu a fost modificată.

Pe Windows, dacă OpenSSL nu poate genera cheile sintetice, setează `OPENSSL_CONF` către `extras/ssl/openssl.cnf` din instalarea PHP. `php vendor/bin/phpunit` evită limita de dimensiune a mediului la subprocesele create de `artisan test` pe acest host.

## Publicare și pachet

`.env.production.example` și `deploy/forge-zero-downtime.sh` pregătesc instanța Forge. După primul deploy rulează `php artisan gnosix:admin`, configurează SMTP din admin, înregistrează passkey și verifică pe domeniul final. `gnosix:check --production` permite inițializarea înainte de SMTP; `gnosix:check --launch` verifică suplimentar pilotul și indexarea.

```powershell
python scripts/package-release.py
```

Arhiva `build/gnosix-atlas-forge-0.10.1.zip` include sursele, traducerile, frontend-ul compilat și documentația, fără `.env`, `vendor`, baze de date, loguri sau sesiuni. Necesită Composer pe server. Forge preia în mod normal codul din Git.

Modelele HTML sunt păstrate în `design/concepts`, în afara document root-ului. Sursele landing-ului sunt în `resources/views/landing`, iar administrarea în `resources/views/admin` și `app/Http/Controllers/Admin`. Fonturile sunt locale, cu licența inclusă. Datele clădirii din landing sunt demonstrative.
