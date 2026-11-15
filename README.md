# GNOSIX Building Management

Laravel 13 / Blade / Vite, pentru `https://gnosix.zion3d.ro` pe Laravel Forge. Landing Atlas și administrare separată a platformei. Produsul pentru clienți și administrarea clădirilor sunt încă în dezvoltare.

## Implementat

- Landing Atlas responsive, straturi interactive, scenarii, contact direct în pagină, confidențialitate, sitemap și SEO.
- Landing bilingv: română implicit la `/`, engleză la `/en`, selector RO/EN și confidențialitate în ambele limbi.
- Contact direct în pagină: nume complet, email, telefon, șase subiecte cu pilotul selectat implicit și mesaj. Toate câmpurile sunt obligatorii, cu validare email și telefon internațional pe server.
- SEO multilingv: canonical, hreflang reciproc și x-default, sitemap cu alternative, Open Graph/Twitter localizat, imagini RO/EN și JSON-LD.
- `/admin/login`: parolă și passkey WebAuthn, cu cookie, guard și tabel separate de viitorii clienți.
- Setări platformă cu taburi General, GNOSIX · Site & contact, Email & SMTP, Securitate & passkeys.
- Setările de produs sunt în baza de date și se modifică din admin, fără `.env`, deploy sau `config:cache`. Valorile vechi sunt importate o singură dată de migrare.
- SMTP salvat criptat în baza de date și aplicat tuturor emailurilor sistemului; test de livrare către administrator.
- Comanda `gnosix:admin` pentru provisioning din `ADMIN_EMAIL` și `ADMIN_PASSWORD`, fără a suprascrie parola existentă la rerulare.
- Management și revocare passkeys, reconfirmare pentru modificări sensibile, rate limiting și invalidarea sesiunilor la resetarea credențialelor.
- Script Forge cu migrații și verificări înaintea activării release-ului.

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

- [Administrare, SMTP, configurare passkey și recuperare](docs/ADMIN.md)
- [Publicare pe Laravel Forge](docs/DEPLOY-FORGE.md)
- [Formular de contact, traduceri și SEO](docs/CONTACT-AND-SEO.md)
- [Setări de sistem și migrarea vechilor valori din mediu](docs/SYSTEM-SETTINGS.md)
- [Brief-ul și explorările inițiale](docs/DESIGN-BRIEF.md)

Parolele nu se trimit ca argumente Artisan și nu se pun în Git. După provisioning, `ADMIN_PASSWORD` poate fi eliminată din mediu. Loginul folosește hash-ul din baza de date; schimbarea valorii din mediu nu resetează automat parola. Folosește explicit `gnosix:admin --reset-password` când este necesar.

## Verificare

```powershell
php vendor/bin/phpunit
npm run build
composer validate --no-check-publish
php artisan gnosix:check
```

56 de teste / 354 de aserțiuni au verificat setările de sistem și importul lor, contactul bilingv, emailul și telefonul, SEO, izolarea admin/client, provisioning-ul, criptarea și aplicarea SMTP globală, precum și înregistrarea și loginul WebAuthn cu semnături ES256 reale generate de un autentificator sintetic. Testele resping origini/RP ID/challenge/semnături incorecte, lipsa verificării utilizatorului, credențiale revocate și reutilizarea challenge-ului.

Au fost verificate în browser loginul cu parolă, setările SMTP, un test de email către un SMTP sink local, logoutul și paginile mobile. Nu a fost înregistrată o cheie pe dispozitivul real al utilizatorului și nu s-a trimis email extern. Infrastructura Forge nu a fost modificată.

Pe Windows, dacă OpenSSL nu poate genera cheile sintetice, setează `OPENSSL_CONF` către `extras/ssl/openssl.cnf` din instalarea PHP. `php vendor/bin/phpunit` evită limita de dimensiune a mediului la subprocesele create de `artisan test` pe acest host.

## Publicare și pachet

`.env.production.example` și `deploy/forge-zero-downtime.sh` pregătesc instanța Forge. După primul deploy rulează `php artisan gnosix:admin`, configurează SMTP din admin, înregistrează passkey și verifică pe domeniul final. `gnosix:check --production` permite inițializarea înainte de SMTP; `gnosix:check --launch` verifică suplimentar pilotul și indexarea.

```powershell
python scripts/package-release.py
```

Arhiva `build/gnosix-atlas-forge-0.4.1.zip` include sursele, traducerile, frontend-ul compilat și documentația, fără `.env`, `vendor`, baze de date, loguri sau sesiuni. Necesită Composer pe server. Forge preia în mod normal codul din Git.

Modelele HTML sunt păstrate în `design/concepts`, în afara document root-ului. Sursele landing-ului sunt în `resources/views/landing`, iar administrarea în `resources/views/admin` și `app/Http/Controllers/Admin`. Fonturile sunt locale, cu licența inclusă. Datele clădirii din landing sunt demonstrative.
