# GNOSIX Building Management — Atlas

Landing page în **Laravel 13 / Blade / Vite**, pregătit pentru `https://gnosix.zion3d.ro` pe Laravel Forge. GNOSIX este un produs în dezvoltare pentru memoria tehnică a clădirilor; pagina invită hoteluri, dezvoltatori și administratori să participe la validarea pilot.

## Implementat

- Atlas ca pagină principală Laravel, cu secțiuni Blade separate și ilustrație axonometrică originală.
- Straturi interactive, scenarii pe segmente, meniu mobil, dialog accesibil, pauză și preferință pentru reducerea mișcării.
- CSS, JavaScript și fonturi Manrope locale, compilate și versionate prin Vite.
- Formular real către `raduta.alex@gmail.com`, prin expeditor și transport configurabile pe server; validare, CSRF, honeypot și limită de 5 cereri/minut și 20/zi per IP.
- Erorile SMTP nu produc o confirmare de succes. Formularul este indisponibil dacă livrarea nu este activată sau dacă transportul este doar `log`/`array`.
- Informare de confidențialitate, canonical, Open Graph cu PNG 1200×630, robots configurabil, sitemap și pagini de eroare.
- Configurație de producție, script Forge zero-downtime și comandă de verificare înaintea lansării.

## Pornire locală

PHP 8.4 pentru setul de dependențe blocat, Composer, Node 22.12+ sau 24 LTS și npm.

```powershell
composer install
# Numai dacă .env nu există deja:
Copy-Item .env.example .env
php artisan key:generate
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8035
```

Deschide `http://127.0.0.1:8035`. Dacă păstrezi un `.env` anterior, setează `SESSION_DRIVER=file`, `CACHE_STORE=file` și `QUEUE_CONNECTION=sync` pentru a rula landing-ul fără bază de date. Nu este necesar un worker sau scheduler.

Pentru email, completează configurația SMTP, `MAIL_FROM_ADDRESS`, `GNOSIX_PILOT_RECIPIENT` și activează `GNOSIX_PILOT_ENABLED=true`. Implicit, formularul este oprit, iar indexarea este dezactivată în mediul local. Configurația nu este o dovadă a livrării în inbox.

## Verificare

```powershell
php vendor/bin/phpunit
npm run build
composer validate --no-check-publish
php artisan gnosix:check
```

Au trecut 11 teste PHP / 60 de aserțiuni, build-ul Vite, compilarea rutelor și a view-urilor și verificarea HTTP a paginilor publice. În browser au fost verificate Atlas și formularul pe desktop/mobil, inclusiv o trimitere către un SMTP sink pe loopback. Un POST real fără token CSRF a fost refuzat cu HTTP 419. Nu a fost trimis email către Gmail și nu a fost modificată infrastructura de producție.

Pe acest host Windows, `php artisan test` poate depăși limita de dimensiune a mediului atunci când creează un subproces. `php vendor/bin/phpunit` rulează aceeași suită direct.

## Publicare pe Forge

Urmează [ghidul de publicare](docs/DEPLOY-FORGE.md). Folosește `.env.production.example` și `deploy/forge-zero-downtime.sh`. Comanda `php artisan gnosix:check --launch` blochează activarea unui release configurat incomplet, fără să trimită email.

Pachet local de surse și frontend compilat:

```powershell
python scripts/package-release.py
```

Rezultat: `build/gnosix-atlas-forge-0.1.0.zip` și checksum SHA256. Pachetul nu include `.env`, `vendor`, loguri, sesiuni, baze de date sau conceptele alternative. Necesită `composer install --no-dev` pe server. Publicarea standard pe Forge folosește repository-ul Git, nu arhiva.

## Structură

- `resources/views/landing`: secțiunile Atlas, dialogul și formularul.
- `resources/css` și `resources/js/app.js`: sursele frontend; `public/build` este generat la deploy.
- `app/Http/Controllers/PilotEnquiryController.php`: trimiterea cererii pilot.
- `app/Http/Requests/PilotEnquiryRequest.php`: validare.
- `config/gnosix.php`: domeniu, indexare și program pilot.
- `design/concepts`: cele cinci modele HTML originale, păstrate în afara document root-ului. Deschide `design/concepts/index.html` local; regenerarea lor cu `python scripts/build-concepts.py` nu modifică Blade.
- `docs/DESIGN-BRIEF.md`: contextul inițial; `docs/DEPLOY-FORGE.md`: configurația de operare.

Produsul rămâne în dezvoltare. Proprietățile și datele din ilustrație sunt exemple. Nu sunt afișate clienți, testimoniale sau rezultate inventate. Licența fontului local este inclusă în `resources/fonts/OFL-Manrope.txt`.
