# Publicare GNOSIX pe Laravel Forge

Destinație: `https://gnosix.zion3d.ro` · Repository: `matrixn/gnosix-building-management` · Ramură: `main`.

## Configurarea site-ului

1. Creează site-ul Laravel în Forge pe VPS, cu domeniul `gnosix.zion3d.ro`, PHP **8.4** și document root **`/public`**. Selectează repository-ul și ramura `main`.
2. Activează zero-downtime la crearea site-ului. Adaugă `storage` la shared paths, pentru ca sesiunile și limitarea cererilor să persiste între release-uri. `.env` este partajat implicit de Forge.
3. Instalează Node **22.12+** sau **24 LTS**, cu npm. Versiunile frontend sunt blocate în `package-lock.json`; folosește `npm ci` la deploy.
4. Configurează DNS pentru subdomeniu către VPS. Dacă există un record AAAA, acesta trebuie să indice tot un server care deservește site-ul. Emite certificatul TLS în Forge și activează redirecționarea HTTP către HTTPS.
5. În **Environment**, pornește de la `.env.production.example`. Generează o singură dată cheia aplicației cu PHP-ul site-ului: `php artisan key:generate --force`. Nu schimba cheia la fiecare deploy.
6. Completează configurația SMTP și expeditorul autentificat. Destinatarul cererilor este `raduta.alex@gmail.com`; acesta **nu** este implicit și contul SMTP. Gmail poate primi mesaje de la orice furnizor SMTP configurat corect.
7. După configurare, setează `GNOSIX_PILOT_ENABLED=true` și `GNOSIX_INDEXABLE=true` pentru domeniul final. Pe staging păstrează indexarea dezactivată și folosește un transport de test, fără expediere către persoane reale.
8. Lipește conținutul `deploy/forge-zero-downtime.sh` în editorul Deploy Script din Forge. Macro-urile sunt interpretate de Forge; nu executa fișierul cu `bash` ca script independent.

Landing page-ul folosește sesiuni și cache în fișiere și trimite cererile sincron. Nu are nevoie de bază de date, migrații, scheduler sau queue worker. `storage` și `bootstrap/cache` trebuie să fie inscriptibile de utilizatorul site-ului; păstrează permisiunile Forge și nu utiliza `chmod 777`.

## Email

```dotenv
GNOSIX_PILOT_RECIPIENT=raduta.alex@gmail.com
GNOSIX_PILOT_ENABLED=true
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=HOSTUL_FURNIZORULUI
MAIL_PORT=465
MAIL_USERNAME=UTILIZATORUL_SMTP
MAIL_PASSWORD=SECRETUL_SMTP
MAIL_FROM_ADDRESS=EXPEDITOR_VERIFICAT
MAIL_FROM_NAME="GNOSIX Building Management"
```

Folosește combinația de port și criptare indicată de furnizor. Pentru STARTTLS, schema este `smtp`, de obicei cu portul `587`. Verifică SPF, DKIM și DMARC conform furnizorului. Nu salva parola SMTP în repository și nu folosi transportul `log`, `array` ori fallback spre log pentru formularul public: aplicația refuză confirmarea unei astfel de trimiteri.

Mesajele au un subiect fix, expeditorul configurat pe server și `Reply-To` egal cu adresa validată a solicitantului. Nu trimitem confirmări automate către vizitatori și nu salvăm solicitările într-o bază de date. Livrarea SMTP este sincronă; o eroare afișează un mesaj și păstrează câmpurile în sesiune pentru reîncercare. În caz de eroare după acceptarea SMTP, o reîncercare poate produce un duplicat; nu există retry automat.

## Verificare și activare

Scriptul pregătește release-ul, instalează dependențele, compilează Vite și cache-urile, apoi execută:

```bash
php artisan gnosix:check --launch
```

Comanda verifică configurația și resursele; **nu deschide o conexiune SMTP și nu trimite email**. Dacă o verificare eșuează, macro-ul de activare nu este atins. Nu activează o configurație cu debug pornit, sesiuni nesecurizate, formular oprit sau indexare dezactivată.

La prima publicare verifică în browser:

- `/`: pagina Atlas, fără galeria și etichetele de studiu de design.
- `/confidentialitate`: informarea privind formularul. Confirmă că identitatea responsabilului, furnizorii și practicile de păstrare descrise corespund operării reale.
- `/robots.txt` și `/sitemap.xml`: domeniul final și indexare permisă.
- `/up`: HTTP 200; configurează-l ca health check în Forge.
- Formular: trimite o cerere controlată și verifică primirea efectivă în Gmail, inclusiv Spam și funcționarea Reply-To. Un răspuns SMTP acceptat nu dovedește livrarea în inbox.
- Mobil: meniul, dialogul, validarea și confirmarea trimiterii.

Verificările locale au inclus 11 teste PHP, build Vite, cache pentru rute și Blade, HTTP pentru paginile de mai sus, refuzarea unui POST fără CSRF și trimiterea din browser către un SMTP sink pe loopback. Nu au fost trimise mesaje către Gmail. DNS, TLS, permisiunile și SMTP-ul VPS-ului rămân de verificat pe infrastructura reală.

## Revenire

În Forge selectează un release anterior funcțional și folosește mecanismul de revenire disponibil pentru site. Configurația `.env` și `storage` sunt partajate: revenirea codului nu revine automat și asupra lor. Nu există migrații pentru acest landing. Poți opri temporar formularul cu `GNOSIX_PILOT_ENABLED=false`, urmat de refacerea cache-ului de configurare; pagina rămâne disponibilă.

## Referințe

- [Laravel Forge — deployments și macro-uri](https://laravel.com/forge/docs/sites/deployments)
- [Laravel — publicarea aplicației și document root](https://laravel.com/framework/docs/13.x/deployment)
- [Laravel — mail](https://laravel.com/framework/docs/13.x/mail)
