# GNOSIX Building Management

Laravel 13 / Blade / Vite, pentru `https://gnosix.zion3d.ro` pe Laravel Forge. Landing Atlas, identitate client Genesis și administrare separată a platformei. Organizațiile și profilarea multi-tenant sunt disponibile; modulele operaționale ale clădirilor sunt în dezvoltare.

## Checklist de livrare

`[x]` înseamnă implementat și verificat; `[ ]` înseamnă planificat sau incomplet. Acest checklist se actualizează la fiecare task. O demonstrație vizuală nu înseamnă că modulul operațional este livrat.

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
- [x] Schimbarea profilului/formei fără ștergerea organizației, membrilor, datelor sau opțiunilor anterioare; test de upgrade viitor cu clădiri/dovezi-fixture.
- [x] UI pentru organizații/setări/membri și pagini de context al modulelor; acestea nu sunt încă module CRUD operaționale.
- [x] Teste de autorizare, IDOR, izolare între utilizatori/organizații, CSRF, profile indisponibile, concurență și regresii Task 001.

### Etape următoare — neimplementate operațional

- [ ] Activarea profilului Construction & Development și a fluxurilor sale.
- [ ] Activarea profilului Property & Facility Management și a fluxurilor sale.
- [ ] CRUD de clădiri/proprietăți și spații/camere, cu tipuri independente de industrie.
- [ ] Mentenanță, echipamente, intervenții, calendar și QR în aplicația operațională.
- [ ] Încărcarea/stocarea/versionarea și revizuirea documentelor/dovezilor reale.
- [ ] Evidence Engine operațional: proveniență și detectarea contradicțiilor.
- [ ] Time Machine operațional și Building DNA cu relații reale între active.
- [ ] Trust Passport, Zero-Entry asistat AI, Handover între organizații și LifeCycle Simulator.
- [ ] Invitații prin email, transfer de proprietar și politici granulare pe resurse.
- [ ] Passkeys pentru clienți, social login și 2FA.
- [ ] API public, import IFC, integrări IoT și ecosistem furnizori.
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
npm run build
composer validate --no-check-publish
php artisan gnosix:check
```

98 de teste / 737 de aserțiuni au verificat organizațiile și apartenențele, profilarea, capabilitățile, izolarea tenantului, ciornele, setările de sistem și importul lor, contactul bilingv, emailul și telefonul, SEO, izolarea admin/client, provisioning-ul, criptarea și aplicarea SMTP globală, precum și înregistrarea și loginul WebAuthn cu semnături ES256 reale generate de un autentificator sintetic. Testele resping origini/RP ID/challenge/semnături incorecte, lipsa verificării utilizatorului, credențiale revocate și reutilizarea challenge-ului.

Au fost verificate în browser loginul cu parolă, setările SMTP, un test de email către un SMTP sink local, logoutul și paginile mobile. Nu a fost înregistrată o cheie pe dispozitivul real al utilizatorului și nu s-a trimis email extern. Infrastructura Forge nu a fost modificată.

Pe Windows, dacă OpenSSL nu poate genera cheile sintetice, setează `OPENSSL_CONF` către `extras/ssl/openssl.cnf` din instalarea PHP. `php vendor/bin/phpunit` evită limita de dimensiune a mediului la subprocesele create de `artisan test` pe acest host.

## Publicare și pachet

`.env.production.example` și `deploy/forge-zero-downtime.sh` pregătesc instanța Forge. După primul deploy rulează `php artisan gnosix:admin`, configurează SMTP din admin, înregistrează passkey și verifică pe domeniul final. `gnosix:check --production` permite inițializarea înainte de SMTP; `gnosix:check --launch` verifică suplimentar pilotul și indexarea.

```powershell
python scripts/package-release.py
```

Arhiva `build/gnosix-atlas-forge-0.7.0.zip` include sursele, traducerile, frontend-ul compilat și documentația, fără `.env`, `vendor`, baze de date, loguri sau sesiuni. Necesită Composer pe server. Forge preia în mod normal codul din Git.

Modelele HTML sunt păstrate în `design/concepts`, în afara document root-ului. Sursele landing-ului sunt în `resources/views/landing`, iar administrarea în `resources/views/admin` și `app/Http/Controllers/Admin`. Fonturile sunt locale, cu licența inclusă. Datele clădirii din landing sunt demonstrative.
