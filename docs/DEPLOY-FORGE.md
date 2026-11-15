# Publicare GNOSIX pe Laravel Forge

Destinație: `https://gnosix.zion3d.ro` · Repository: `matrixn/gnosix-building-management` · Ramură: `main`.

## Prima instalare

1. Creează site Laravel cu PHP 8.4, document root `/public` și zero-downtime. Selectează repository-ul și ramura `main`.
2. Partajează `storage` între release-uri; `.env` este partajat implicit de Forge. Configurează o bază de date persistentă MySQL/PostgreSQL și valorile `DB_*`. Adminul, passkey-urile și setările SMTP necesită migrații; landing-ul nu mai este o aplicație fără bază de date.
3. Instalează Node 22.12+ sau 24 LTS și npm. Folosește `npm ci`, cu lockfile-ul din repository.
4. Configurează DNS către VPS, inclusiv eventualul AAAA, certificatul TLS și redirecționarea HTTP către HTTPS.
5. Pornește de la `.env.production.example`. Generează `APP_KEY` o singură dată și păstreaz-o între deploy-uri. Completează `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `PASSKEY_RP_ID` și `PASSKEY_ORIGIN` conform [instrucțiunilor admin](ADMIN.md). Contactul și indexarea sunt dezactivate implicit la instalare nouă; se configurează ulterior din administrare.
6. Lipește `deploy/forge-zero-downtime.sh` în editorul Deploy Script din Forge. Macro-urile sunt interpretate de Forge; nu executa fișierul ca script Bash independent.
7. Efectuează primul deploy. Scriptul instalează dependențele, compilează frontend-ul, rulează `migrate --force`, cache-uiește rutele/view-urile și verifică setările structurale de producție înainte de activare.
8. După inițializare rulează din Forge Commands:

```bash
php artisan gnosix:admin
```

9. Autentifică-te la `/admin/login`, salvează SMTP din **Setări → Email & SMTP** și testează livrarea către adresa administratorului. Adaugă passkey din **Securitate & passkeys**. Pașii de înregistrare, domeniul și recuperarea sunt în [ADMIN.md](ADMIN.md).
10. După verificarea livrării și a conținutului, deschide **Setări → GNOSIX · Site & contact**, verifică adresa canonică și destinatarul, apoi activează contactul și indexarea. Se aplică imediat. Verifică lansarea:

```bash
php artisan gnosix:check --launch
```

Deploy-ul folosește `gnosix:check --production`, care permite primul acces la admin înaintea configurării SMTP. `--launch` verifică suplimentar activarea pilotului și indexării. Niciuna dintre comenzi nu trimite email și nu dovedește livrarea în inbox.

`storage` și `bootstrap/cache` trebuie să fie inscriptibile de utilizatorul site-ului. Nu folosi `chmod 777`. Păstrează baza de date și `APP_KEY` în backup: fără cheia inițială, configurația SMTP criptată nu poate fi citită. Landing-ul trimite sincron; nu este necesar un worker sau scheduler. Dacă adaugi ulterior emailuri în queue, și acestea folosesc managerul SMTP centralizat.

## Migrarea setărilor existente

Migrațiile importă o singură dată vechile valori de produs din mediu în baza de date. Lasă-le disponibile până la primul deploy al acestei versiuni, apoi verifică-le în **GNOSIX · Site & contact**. Ulterior, administrarea este singura sursă pentru aceste opțiuni și variabilele vechi pot fi eliminate. Detalii în [SYSTEM-SETTINGS.md](SYSTEM-SETTINGS.md).

## SMTP și cereri pilot

Configurația salvată în admin are prioritate față de `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` și `MAIL_FROM_NAME`. Valorile din mediu servesc doar la inițializare dacă nu există încă o configurație salvată. `MAIL_MAILER` nu poate comuta sistemul pe log/array/sendmail: toate mailerele aplicației folosesc SMTP.

Folosește TLS implicit sau STARTTLS conform furnizorului. În producție TLS și verificarea certificatului sunt obligatorii. Configurează SPF, DKIM și DMARC după instrucțiunile furnizorului. Destinatarul contactului este configurat din tabul GNOSIX; contul SMTP și emailul administratorului pot fi diferite.

O eroare SMTP nu afișează succes; datele cererii pilot sunt păstrate temporar în sesiune pentru reîncercare. Nu există retry automat. Într-o situație în care acceptarea SMTP are loc înaintea unei erori de conexiune, o reîncercare manuală poate produce un duplicat.

## Verificare după deploy

- `/` și `/confidentialitate`: conținut, linkuri, fonturi și formular.
- `/robots.txt`, `/sitemap.xml`: domeniul final și indexare permisă după lansare.
- `/up`: HTTP 200, utilizabil ca health check Forge.
- `/admin/login`: parola funcționează, logoutul închide accesul, paginile au `noindex`.
- Admin SMTP: mesaj de test primit în căsuța administratorului, inclusiv verificarea Spam.
- Passkey: înregistrare și login pe domeniul final, cu un dispozitiv real și o cheie de rezervă.
- Formular pilot: verifică primirea efectivă în Gmail și funcționarea Reply-To.
- Mobil: meniul public, formularul și setările admin.

Înaintea colectării datelor reale, confirmă că informarea de confidențialitate corespunde furnizorilor și practicilor efective de operare.

## Revenire

Folosește mecanismul Forge de revenire la un release anterior. `.env`, `storage` și baza de date sunt partajate; revenirea codului nu le revine automat. Migrația admin adaugă tabele și nu modifică utilizatorii existenți. Nu rula automat `migrate:rollback` la revenire: ar șterge conturile platformei, passkey-urile și setările. Poți opri formularul public direct din tabul GNOSIX, fără modificarea mediului sau a cache-ului de configurare.

Referințe: [Forge deployments](https://laravel.com/forge/docs/sites/deployments), [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment), [Laravel mail](https://laravel.com/framework/docs/13.x/mail).
