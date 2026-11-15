# Administrarea platformei GNOSIX

## Delimitarea conturilor

`/admin/login` este destinat echipei care operează platforma, nu administratorilor de clădiri care vor fi clienți GNOSIX.

| Nivel | Identitate | Autentificare | Date și acces |
| --- | --- | --- | --- |
| Platformă | `PlatformAdmin` / `platform_admins` | Guard `platform_admin`, cookie `gnosix_platform_session` cu path `/admin` | Setări globale SMTP și propriile passkeys |
| Viitor client | `User` / `users` | Guard `web`, sesiune separată | Viitoarele date, clădiri și permisiuni ale clientului |

Un client nu primește acces în platform admin, nici dacă are același email sau același ID numeric. Nu există înregistrare publică pentru platform admin și nu există un câmp `is_admin` pe conturile clienților. Aplicația pentru clienți nu este încă implementată.

## Prima instalare pe Forge

1. Configurează o bază de date persistentă în Forge și valorile `DB_*` din Environment. Exemplul de producție folosește MySQL. Pentru PostgreSQL adaptează driverul și portul. SQLite este potrivit pentru dezvoltare; pe Forge fișierul trebuie să fie partajat între release-uri dacă alegi acest driver.
2. Păstrează o singură valoare `APP_KEY` pentru această instanță. Configurează HTTPS și sesiuni securizate conform `.env.production.example`.
3. În Environment completează:

```dotenv
ADMIN_EMAIL=
ADMIN_PASSWORD=
PASSKEY_ORIGIN=https://gnosix.zion3d.ro
PASSKEY_RP_ID=gnosix.zion3d.ro
```

`ADMIN_EMAIL` trebuie să fie adresa ta reală de administrator. `ADMIN_PASSWORD` trebuie să aibă 12–255 caractere. Nu folosi parola SMTP ca parolă de administrator. Pentru caractere speciale în `.env`, folosește ghilimele simple și sintaxa acceptată de dotenv.

4. După primul deploy, rulează din **Forge → Commands**, în directorul release-ului curent, cu PHP-ul site-ului:

```bash
php artisan migrate --force
php artisan gnosix:admin
```

Scriptul de deploy include deja migrațiile. Comanda de administrator este intenționat separată și se rulează după inițializare. La prima execuție creează contul cu parola hash-uită în `platform_admins`. La rerulare nu suprascrie parola existentă. Valorile sunt citite la execuție din mediul procesului sau `.env`, inclusiv după `config:cache`; parola de inițializare nu este adăugată în configurația cache-uită și nu este afișată în output.

5. Deschide `https://gnosix.zion3d.ro/admin/login` și autentifică-te cu parola. Configurează SMTP din **Setări → Email & SMTP**, apoi passkey din **Setări → Securitate & passkeys**.
6. După inițializare poți elimina `ADMIN_PASSWORD` din Environment. Loginul folosește hash-ul din baza de date, nu parola din fișierul de mediu. Comanda nu este rulată automat la fiecare deploy.

## SMTP centralizat

Pagina **Email & SMTP** permite salvarea serverului, portului, criptării, utilizatorului, parolei și expeditorului. Configurația salvată în `platform_settings` are prioritate față de valorile inițiale `MAIL_*` din mediu. Întregul obiect de setări SMTP este criptat folosind cheia aplicației; parola nu este populată în HTML și nu este pusă în input-ul vechi la erori de validare.

- Lasă parola goală pentru a păstra parola existentă. Pentru înlocuire, introdu una nouă.
- Pentru relay fără autentificare, golește utilizatorul și bifează ștergerea parolei salvate.
- Folosește TLS implicit (`smtps`, de regulă 465) sau STARTTLS (`smtp`, de regulă 587), conform furnizorului. În producție TLS este obligatoriu și verificarea certificatului rămâne activă.
- „Trimite email de test” folosește configurația salvată și trimite **doar către emailul administratorului autentificat**. Nu poate fi folosit pentru trimitere către adrese arbitrare din request.
- Salvarea nu expediază automat un email. Mesajul de succes al testului arată acceptarea SMTP; verifică primirea efectivă în Inbox/Spam.
- Toate emailurile aplicației prin API-urile Laravel Mail, inclusiv mailere numite, mailere create la cerere, notificări și emailuri procesate în queue, folosesc SMTP centralizat. Transporturile `log`, `array` și fallback nu sunt folosite la livrarea reală. `Mail::fake()` rămâne disponibil exclusiv pentru teste.
- Managerul reîncarcă setările pentru fiecare mesaj, inclusiv în procese persistente. Nu trebuie refăcut `config:cache` când salvezi SMTP din admin.

Activarea formularului, destinatarul mesajelor, indexarea SEO și adresa canonică sunt în **Setări → GNOSIX · Site & contact**. Nu se configurează în `.env`; salvarea se aplică imediat. Vezi [setările de sistem](SYSTEM-SETTINGS.md) pentru importul valorilor vechi. Aceste opțiuni nu schimbă contul administratorului sau credențialele SMTP.

## Înregistrarea și folosirea unui passkey

În Forge, domeniul final și certificatul HTTPS trebuie să funcționeze. Configurează:

```dotenv
APP_URL=https://gnosix.zion3d.ro
PASSKEY_ORIGIN=https://gnosix.zion3d.ro
PASSKEY_RP_ID=gnosix.zion3d.ro
```

`PASSKEY_ORIGIN` este originea completă, fără cale și fără slash final. `PASSKEY_RP_ID` este doar hostname-ul: fără `https://`, port sau `/admin`. Nu seta `zion3d.ro` ca RP ID comun dacă intenția este ca passkey-urile platformei să fie strict legate de `gnosix.zion3d.ro`.

Rulează `php artisan config:cache` sau efectuează un nou deploy după schimbarea valorilor din mediu.

1. Autentifică-te cu email și parolă.
2. Deschide **Setări → Securitate & passkeys**.
3. Introdu un nume recognoscibil, precum „Laptop personal” sau „Cheie de rezervă”, apoi apasă **Adaugă passkey**.
4. Confirmă în dialogul browserului/sistemului cu Windows Hello, Touch ID, un manager de passkeys ori o cheie de securitate compatibilă. Este cerută verificarea utilizatorului, nu doar prezența dispozitivului.
5. Passkey-ul apare în listă. Deloghează-te și folosește **Autentificare cu passkey** în pagina de login. Nu este necesară introducerea emailului pentru acest flux.
6. Recomandat: adaugă o a doua cheie pe un dispozitiv de rezervă. Poți revoca individual cheile din aceeași pagină.

Browserul și dispozitivul trebuie să suporte chei rezidente/discoverable și verificarea utilizatorului. PIN-ul, amprenta și cheia privată nu sunt primite de aplicație. Serverul păstrează cheia publică și metadatele WebAuthn. Înregistrările aparțin exclusiv `platform_admins`, în `platform_admin_passkeys`.

Originea completă, hash-ul RP ID, challenge-ul, semnătura, verificarea utilizatorului și identitatea proprietarului sunt verificate pe server. Challenge-urile sunt legate de sesiune, expiră după două minute și sunt consumate la verificare. Configurația nu permite implicit subdomenii sau domenii alternative.

### Dezvoltare locală

Pentru `http://localhost:8035`:

```dotenv
APP_URL=http://localhost:8035
PASSKEY_ORIGIN=http://localhost:8035
PASSKEY_RP_ID=localhost
```

Folosește exact acel hostname în browser. Pentru `127.0.0.1`, schimbă toate trei valorile corespunzător și verifică suportul browserului. Pe alte adrese HTTP din rețeaua locală este necesar HTTPS. Cheile locale nu sunt chei pentru domeniul de producție.

## Reconfirmare și recuperare

Autentificarea prin parolă sau passkey confirmă identitatea pentru zece minute. După acest interval, salvarea SMTP, testul de email, adăugarea și revocarea passkeys cer reconfirmarea parolei din tabul Securitate. Alternativ, deloghează-te și autentifică-te din nou cu passkey.

Pentru resetarea parolei, completează `ADMIN_EMAIL` cu emailul contului existent și `ADMIN_PASSWORD` cu noua parolă, apoi rulează:

```bash
php artisan gnosix:admin --reset-password
```

Comanda actualizează hash-ul și invalidează sesiunile existente. Nu revocă automat passkey-urile.

Dacă ai pierdut dispozitivele sau suspectezi compromiterea lor:

```bash
php artisan gnosix:admin --reset-password --revoke-passkeys
```

Această opțiune revocă toate passkey-urile administratorului indicat; înregistrează chei noi după login. Nu schimbă datele clienților. O adresă `ADMIN_EMAIL` diferită creează un cont nou; nu redenumește sau șterge administratorul precedent.

## Operare și verificări

Fă backup pentru baza de date și pentru cheia aplicației. Pierderea sau înlocuirea `APP_KEY` împiedică decriptarea setărilor SMTP și invalidează sesiunile criptate. Păstrează `storage` partajat între release-uri și limita de cereri activă. Secretele nu trebuie incluse în Git sau în arhiva de release.

Au fost verificate 56 de teste / 354 de aserțiuni: separarea client/admin, parole, provisioning idempotent, sesiuni, criptarea SMTP, transportul global, proprietatea passkeys și validarea criptografică a înregistrării/autentificării WebAuthn. În browser au fost verificate loginul, salvarea SMTP, testul către un SMTP sink pe loopback, logoutul și paginile mobile. Nu a fost înregistrat un passkey pe dispozitivul real al utilizatorului și nu a fost trimis email extern.

Testele WebAuthn generează un autentificator ES256 sintetic. Pe Windows, dacă OpenSSL nu găsește configurația, setează `OPENSSL_CONF` către `extras/ssl/openssl.cnf` din instalarea PHP înainte de `php vendor/bin/phpunit`. Pe acest host calea este `C:\php-8.4.14-nts\extras\ssl\openssl.cnf`.

Referințe: [Spatie Laravel Passkeys](https://spatie.be/docs/laravel-passkeys/v1/installation-setup), [WebAuthn library](https://github.com/web-auth/webauthn-lib).
